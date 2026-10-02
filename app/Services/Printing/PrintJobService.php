<?php

namespace App\Services\Printing;

use App\Jobs\ProcessPrintJob;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\PrintJob;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Documents\DocumentData;
use App\Services\Documents\NumberGenerator;
use App\Services\Documents\PageComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creates print jobs (one row + one item per document) and dispatches the
 * background processor. Small jobs run right after the HTTP response, large
 * ones go to the queue so PDFs are never built inside a request.
 */
class PrintJobService
{
    public const PDF_DISK = 'local';

    public function __construct(
        private NumberGenerator $numbers,
        private AuditLogger $audit,
        private PageComposer $composer,
    ) {}

    /**
     * @param  Collection<int, Model>  $subjects  students or staff (already tenant-scoped)
     * @param  array<string, mixed>  $options
     */
    public function create(School $school, User $user, string $type, IdCardTemplate|CertificateTemplate $template, Collection $subjects, array $options, ?string $ip): PrintJob
    {
        return $this->createJob($school, $user, $type, $template, $subjects->map(fn (Model $s) => [$s, null]), $options, $ip);
    }

    /**
     * Reprint already-issued documents (no new numbers are allocated).
     *
     * @param  Collection<int, IdCard|Certificate>  $documents
     * @param  array<string, mixed>  $options
     */
    public function createReprint(School $school, User $user, string $type, IdCardTemplate|CertificateTemplate $template, Collection $documents, array $options, ?string $ip): PrintJob
    {
        $pairs = $documents->map(fn (IdCard|Certificate $d) => [$d instanceof IdCard ? $d->holder : $d->student, $d]);

        return $this->createJob($school, $user, $type, $template, $pairs, $options + ['reprint' => true], $ip);
    }

    /** @param  Collection<int, array{0: Model, 1: Model|null}>  $pairs */
    private function createJob(School $school, User $user, string $type, IdCardTemplate|CertificateTemplate $template, Collection $pairs, array $options, ?string $ip): PrintJob
    {
        $job = DB::transaction(function () use ($school, $user, $type, $template, $pairs, $options, $ip) {
            $number = $this->numbers->next($school, NumberGenerator::TYPE_PRINT_JOB, '{CODE}-PJ-{YEAR}-{SEQ:5}', null,
                fn (string $n) => PrintJob::withoutGlobalScopes()->where('job_number', $n)->exists());

            $job = new PrintJob;
            $job->forceFill([
                'job_number' => $number,
                'school_id' => $school->id,
                'user_id' => $user->id,
                'type' => $type,
                'template_id' => $template->id,
                'template_name' => $template->name,
                'total_items' => $pairs->count(),
                'status' => 'pending',
                'options' => $options,
                'ip_address' => $ip,
            ])->save();

            $now = now();
            $pairs->values()->chunk(500)->each(function (Collection $chunk, int $chunkIndex) use ($job, $now) {
                DB::table('print_job_items')->insert($chunk->values()->map(fn ($pair, $i) => [
                    'print_job_id' => $job->id,
                    'position' => $chunkIndex * 500 + $i,
                    'subject_type' => $pair[0]->getMorphClass(),
                    'subject_id' => $pair[0]->getKey(),
                    'printable_type' => $pair[1]?->getMorphClass(),
                    'printable_id' => $pair[1]?->getKey(),
                    'status' => 'pending',
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all());
            });

            return $job;
        });

        $this->audit->log('print_job.created', $job, "Print job {$job->job_number}: {$job->total_items} × {$job->typeLabel()} ({$template->name})");
        $this->dispatch($job);

        return $job;
    }

    public function dispatch(PrintJob $job): void
    {
        if (config('queue.default') === 'sync') {
            ProcessPrintJob::dispatchSync($job->id);
        } elseif ($job->total_items <= (int) setting('queue_threshold', 25)) {
            ProcessPrintJob::dispatchAfterResponse($job->id);
        } else {
            ProcessPrintJob::dispatch($job->id);
        }
    }

    /**
     * Issued documents of a job in print order.
     *
     * @return Collection<int, IdCard|Certificate>
     */
    public function documents(PrintJob $job): Collection
    {
        $items = $job->items()->where('status', 'done')->whereNotNull('printable_id')->get(['printable_type', 'printable_id', 'position']);
        $ids = $items->pluck('printable_id');

        $documents = $job->isIdCardJob()
            ? IdCard::forSchool($job->school_id)
                ->with(['holder' => fn ($morph) => $morph->morphWith([Student::class => ['academicYear']]), 'school', 'academicYear'])
                ->whereIn('id', $ids)->get()
            : Certificate::forSchool($job->school_id)->with(['student.academicYear', 'school', 'academicYear'])->whereIn('id', $ids)->get();

        $byId = $documents->keyBy('id');

        return $items->map(fn ($item) => $byId->get($item->printable_id))->filter()->values();
    }

    /** @return array<string, mixed> composed pages */
    public function compose(PrintJob $job, string $mode): array
    {
        $template = $job->template();
        $data = $this->documents($job)->map(fn ($d) => $d instanceof IdCard ? DocumentData::forIdCard($d) : DocumentData::forCertificate($d))->all();

        return $this->composer->compose($template, $data, $job->options ?? [], $mode);
    }

    public function buildPdf(PrintJob $job): string
    {
        $pdf = $this->composer->pdf($this->compose($job, 'pdf'), "{$job->job_number} – {$job->template_name}");
        $path = "print-jobs/{$job->school_id}/".str_replace('/', '-', $job->job_number).'.pdf';
        Storage::disk(self::PDF_DISK)->put($path, $pdf);

        return $path;
    }

    /** Record that a job was printed / downloaded. */
    public function markPrinted(PrintJob $job, string $how): void
    {
        DB::transaction(function () use ($job) {
            $job->forceFill(['print_count' => $job->print_count + 1, 'last_printed_at' => now()])->save();
            $ids = $job->items()->where('status', 'done')->pluck('printable_id');
            $model = $job->isIdCardJob() ? IdCard::class : Certificate::class;
            $model::forSchool($job->school_id)->whereIn('id', $ids)->update([
                'print_count' => DB::raw('print_count + 1'),
                'last_printed_at' => now(),
            ]);
        });

        $this->audit->log("print.{$how}", $job, "{$how} {$job->job_number} ({$job->completed_items} items)");
    }
}
