<?php

namespace App\Console\Commands;

use App\Models\IdCard;
use App\Models\School;
use App\Models\Student;
use App\Services\AuditLogger;
use App\Services\Documents\NumberGenerator;
use App\Support\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently removes students (e.g. demo/test data before real data entry),
 * together with their ID cards and photo files. Print history and the audit
 * log are kept. Always asks for confirmation unless --force is given.
 */
class PurgeStudents extends Command
{
    protected $signature = 'students:purge
        {--school=* : School code(s) to purge, e.g. --school=BWM}
        {--all : Purge the students of every school}
        {--reset-numbering : Restart student ID card numbers at 0001}
        {--force : Do not ask for confirmation}';

    protected $description = 'PERMANENTLY delete students with their ID cards and photos';

    public function handle(TenantContext $tenant, AuditLogger $audit): int
    {
        $codes = array_map('strtoupper', (array) $this->option('school'));
        if (! $this->option('all') && $codes === []) {
            $this->error('Choose --school=CODE (repeatable) or --all.');

            return self::INVALID;
        }

        $schools = School::query()->when(! $this->option('all'), fn ($q) => $q->whereIn('school_code', $codes))->orderBy('name')->get();
        if ($schools->isEmpty()) {
            $this->error('No matching school.');

            return self::FAILURE;
        }

        return $tenant->withoutScope(function () use ($schools, $audit) {
            $rows = $schools->map(fn (School $s) => [
                $s->school_code,
                $s->name,
                Student::withTrashed()->where('school_id', $s->id)->count(),
                IdCard::where('school_id', $s->id)->where('holder_type', 'student')->count(),
                Student::withTrashed()->where('school_id', $s->id)->whereNotNull('photo_path')->count(),
            ]);
            $this->table(['Code', 'School', 'Students', 'Student ID cards', 'Photos'], $rows->all());

            if ($rows->sum(fn ($r) => $r[2]) === 0) {
                $this->info('There are no students to delete.');

                return self::SUCCESS;
            }

            $this->warn('This PERMANENTLY deletes the students above, their ID cards and photo files. It cannot be undone.');
            if (! $this->option('force') && ! $this->confirm('Type "yes" to delete them permanently', false)) {
                $this->info('Cancelled. Nothing was deleted.');

                return self::SUCCESS;
            }

            foreach ($schools as $school) {
                $photos = [];
                $deleted = 0;

                DB::transaction(function () use ($school, &$photos, &$deleted) {
                    Student::withTrashed()->where('school_id', $school->id)->select(['id', 'photo_path'])
                        ->chunkById(500, function ($students) use (&$photos, &$deleted) {
                            $ids = $students->pluck('id');
                            $photos = array_merge($photos, $students->pluck('photo_path')->filter()->all());
                            IdCard::where('holder_type', 'student')->whereIn('holder_id', $ids)->delete();
                            $deleted += Student::withTrashed()->whereIn('id', $ids)->forceDelete();
                        });

                    if ($this->option('reset-numbering')) {
                        DB::table('number_sequences')->where('school_id', $school->id)->where('type', NumberGenerator::TYPE_STUDENT_ID)->delete();
                    }
                });

                Storage::disk('public')->delete($photos);
                $audit->log('student.purged', $school, "Permanently deleted {$deleted} students".($this->option('reset-numbering') ? ' and reset student ID numbering' : ''), ['count' => $deleted], $school->id);
                $this->info("{$school->school_code}: {$deleted} student(s) deleted.");
            }

            return self::SUCCESS;
        });
    }
}
