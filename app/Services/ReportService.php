<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\PrintJob;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

/**
 * Aggregate reports. Every query goes through tenant-scoped models, so a
 * school user only ever sees their own school's numbers.
 */
class ReportService
{
    /** @return array<string, array{title: string, description: string, dated: bool}> */
    public static function definitions(): array
    {
        return [
            'students-by-school' => ['title' => 'Students by school', 'description' => 'Active students per school with gender split.', 'dated' => false],
            'students-by-class' => ['title' => 'Students by class', 'description' => 'Active students per class and stream.', 'dated' => false],
            'staff-by-school' => ['title' => 'Staff by school', 'description' => 'Staff members per school and employment status.', 'dated' => false],
            'ids-generated' => ['title' => 'ID cards generated', 'description' => 'ID cards issued per month, by holder type and status.', 'dated' => true],
            'certificates-generated' => ['title' => 'Certificates generated', 'description' => 'Certificates issued per title / programme.', 'dated' => true],
            'printing-activity' => ['title' => 'Printing activity', 'description' => 'Print jobs and printed items per day and user.', 'dated' => true],
            'academic-years' => ['title' => 'Academic year statistics', 'description' => 'Students, ID cards and certificates per academic year.', 'dated' => false],
        ];
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null}  $filters
     * @return array{headers: array<int, string>, rows: array<int, array<int, mixed>>}
     */
    public function run(string $key, array $filters): array
    {
        $from = $filters['date_from'] ?? null;
        $to = $filters['date_to'] ?? null;
        $dated = fn ($query, string $column = 'created_at') => $query
            ->when($from, fn ($q) => $q->where($column, '>=', $from.' 00:00:00'))
            ->when($to, fn ($q) => $q->where($column, '<=', $to.' 23:59:59'));
        $month = DB::connection()->getDriverName() === 'sqlite' ? "strftime('%Y-%m', created_at)" : "DATE_FORMAT(created_at, '%Y-%m')";
        $day = DB::connection()->getDriverName() === 'sqlite' ? 'date(created_at)' : 'DATE(created_at)';

        return match ($key) {
            'students-by-school' => $this->table(['School', 'Code', 'Male', 'Female', 'Total active'],
                Student::query()->where('status', 'active')
                    ->select('school_id', DB::raw("sum(case when gender = 'male' then 1 else 0 end) as male"),
                        DB::raw("sum(case when gender = 'female' then 1 else 0 end) as female"), DB::raw('count(*) as total'))
                    ->groupBy('school_id')->get()
                    ->map(fn ($r) => [$this->schoolName($r->school_id), $this->schoolCode($r->school_id), (int) $r->male, (int) $r->female, (int) $r->total])),

            'students-by-class' => $this->table(['School', 'Level', 'Class', 'Stream', 'Male', 'Female', 'Total'],
                Student::query()->where('status', 'active')
                    ->select('school_id', 'level', 'class_name', 'stream', DB::raw("sum(case when gender = 'male' then 1 else 0 end) as male"),
                        DB::raw("sum(case when gender = 'female' then 1 else 0 end) as female"), DB::raw('count(*) as total'))
                    ->groupBy('school_id', 'level', 'class_name', 'stream')->orderBy('school_id')->orderBy('class_name')->orderBy('stream')->get()
                    ->map(fn ($r) => [$this->schoolCode($r->school_id), $r->level, $r->class_name, $r->stream, (int) $r->male, (int) $r->female, (int) $r->total])),

            'staff-by-school' => $this->table(['School', 'Code', 'Employment status', 'Total'],
                Staff::query()->select('school_id', 'employment_status', DB::raw('count(*) as total'))
                    ->groupBy('school_id', 'employment_status')->orderBy('school_id')->get()
                    ->map(fn ($r) => [$this->schoolName($r->school_id), $this->schoolCode($r->school_id), ucfirst(str_replace('_', ' ', $r->employment_status)), (int) $r->total])),

            'ids-generated' => $this->table(['Month', 'School', 'Holder', 'Status', 'Cards'],
                $dated(IdCard::query())->select(DB::raw("{$month} as period"), 'school_id', 'holder_type', 'status', DB::raw('count(*) as total'))
                    ->groupBy('period', 'school_id', 'holder_type', 'status')->orderByDesc('period')->get()
                    ->map(fn ($r) => [$r->period, $this->schoolCode($r->school_id), ucfirst($r->holder_type), ucfirst($r->status), (int) $r->total])),

            'certificates-generated' => $this->table(['School', 'Title', 'Programme', 'Valid', 'Revoked', 'Total'],
                $dated(Certificate::query())->select('school_id', 'title', 'program',
                    DB::raw("sum(case when status = 'valid' then 1 else 0 end) as valid"),
                    DB::raw("sum(case when status = 'revoked' then 1 else 0 end) as revoked"), DB::raw('count(*) as total'))
                    ->groupBy('school_id', 'title', 'program')->orderBy('school_id')->orderByDesc('total')->get()
                    ->map(fn ($r) => [$this->schoolCode($r->school_id), $r->title, $r->program, (int) $r->valid, (int) $r->revoked, (int) $r->total])),

            'printing-activity' => $this->table(['Date', 'School', 'User', 'Jobs', 'Items generated', 'Times printed'],
                $dated(PrintJob::query(), 'print_jobs.created_at')->leftJoin('users', 'users.id', '=', 'print_jobs.user_id')
                    ->select(DB::raw(str_replace('created_at', 'print_jobs.created_at', $day).' as day'), 'print_jobs.school_id', 'users.name as user_name',
                        DB::raw('count(*) as jobs'), DB::raw('sum(completed_items) as items'), DB::raw('sum(print_count) as prints'))
                    ->groupBy('day', 'print_jobs.school_id', 'users.name')->orderByDesc('day')->limit(2000)->get()
                    ->map(fn ($r) => [$r->day, $this->schoolCode($r->school_id), $r->user_name ?? '—', (int) $r->jobs, (int) $r->items, (int) $r->prints])),

            'academic-years' => $this->table(['School', 'Academic year', 'Current', 'Students', 'ID cards', 'Certificates'],
                AcademicYear::query()->withCount(['students'])
                    ->orderBy('school_id')->orderByDesc('name')->get()
                    ->map(fn ($y) => [$this->schoolCode($y->school_id), $y->name, $y->is_current ? 'Yes' : '',
                        $y->students_count,
                        IdCard::forSchool($y->school_id)->where('academic_year_id', $y->id)->count(),
                        Certificate::forSchool($y->school_id)->where('academic_year_id', $y->id)->count()])),

            default => abort(404),
        };
    }

    private function table(array $headers, iterable $rows): array
    {
        return ['headers' => $headers, 'rows' => collect($rows)->values()->all()];
    }

    /** @var array<int, School>|null */
    private ?array $schools = null;

    private function school(int $id): ?School
    {
        $this->schools ??= School::all(['id', 'name', 'school_code'])->keyBy('id')->all();

        return $this->schools[$id] ?? null;
    }

    private function schoolName(int $id): string
    {
        return $this->school($id)?->name ?? '';
    }

    private function schoolCode(int $id): string
    {
        return $this->school($id)?->school_code ?? '';
    }
}
