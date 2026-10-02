<?php

namespace App\Jobs;

use App\Models\AcademicYear;
use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\PrintJob;
use App\Models\PrintJobItem;
use App\Services\AuditLogger;
use App\Services\Documents\CertificateIssuer;
use App\Services\Documents\IdCardIssuer;
use App\Services\Printing\PrintJobService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Issues the documents of a print job (allocating numbers) and builds its PDF.
 * Runs inside TenantContext::runFor() so every query is scoped to the job's school.
 */
class ProcessPrintJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public int $tries = 1;

    public function __construct(public int $printJobId) {}

    public function handle(TenantContext $tenant, IdCardIssuer $idCards, CertificateIssuer $certificates, PrintJobService $service, AuditLogger $audit): void
    {
        $job = PrintJob::withoutGlobalScopes()->find($this->printJobId);
        if (! $job || in_array($job->status, ['completed', 'cancelled'], true)) {
            return;
        }

        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        $tenant->runFor($job->school_id, function () use ($job, $idCards, $certificates, $service, $audit) {
            try {
                $job->forceFill(['status' => 'processing', 'started_at' => $job->started_at ?? now(), 'error_message' => null])->save();
                $this->issueDocuments($job, $idCards, $certificates);
                $job->refresh();

                if ($job->completed_items === 0) {
                    $job->forceFill(['status' => 'failed', 'completed_at' => now(), 'error_message' => 'No documents could be generated.'])->save();

                    return;
                }

                $path = $service->buildPdf($job);
                $job->forceFill(['status' => 'completed', 'file_path' => $path, 'completed_at' => now()])->save();
                $audit->log('print_job.completed', $job, "Print job {$job->job_number} completed ({$job->completed_items} ok, {$job->failed_items} failed)", [], $job->school_id, $job->user_id);
            } catch (Throwable $e) {
                report($e);
                $job->forceFill(['status' => 'failed', 'completed_at' => now(), 'error_message' => mb_substr($e->getMessage(), 0, 1000)])->save();
            }
        });
    }

    private function issueDocuments(PrintJob $job, IdCardIssuer $idCards, CertificateIssuer $certificates): void
    {
        $template = $job->template();
        if (! $template) {
            throw new \RuntimeException('The template of this print job no longer exists.');
        }

        $year = ($yearId = $job->option('academic_year_id')) ? AcademicYear::find($yearId) : null;

        $job->items()->where('status', 'pending')->with('subject.school')->chunkById(100, function ($items) use ($job, $template, $year, $idCards, $certificates) {
            foreach ($items as $item) {
                /** @var PrintJobItem $item */
                try {
                    if (PrintJob::withoutGlobalScopes()->whereKey($job->id)->value('status') === 'cancelled') {
                        return false;
                    }

                    $printable = $item->printable_id ? $this->existingPrintable($item) : null;
                    if (! $printable) {
                        $subject = $item->subject;
                        if (! $subject || $subject->school_id !== $job->school_id) {
                            throw new \RuntimeException('Record not found in this school.');
                        }

                        $printable = $job->isIdCardJob()
                            ? $idCards->issue($subject, $template, $year, $job->option('mode', IdCardIssuer::MODE_REUSE), $job->user_id)
                            : $certificates->issue($subject, $template, [
                                'title' => $job->option('title'),
                                'program' => $job->option('program'),
                                'description' => $job->option('description'),
                                'issued_on' => $job->option('issued_on', now()->toDateString()),
                            ], $year, $job->user_id);
                    }

                    $item->forceFill(['printable_type' => $printable->getMorphClass(), 'printable_id' => $printable->getKey(), 'status' => 'done', 'error' => null])->save();
                    DB::table('print_jobs')->where('id', $job->id)->increment('completed_items');
                } catch (Throwable $e) {
                    $item->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)])->save();
                    DB::table('print_jobs')->where('id', $job->id)->increment('failed_items');
                }
            }
        });
    }

    private function existingPrintable(PrintJobItem $item): IdCard|Certificate|null
    {
        $class = $item->printable_type === 'certificate' ? Certificate::class : IdCard::class;

        return $class::find($item->printable_id);
    }
}
