<?php

namespace App\Models;

use App\Models\Concerns\IsPerson;
use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'academic_year_id', 'admission_number', 'first_name', 'middle_name', 'last_name', 'gender', 'date_of_birth',
    'nationality', 'level', 'class_name', 'stream', 'combination', 'entry_year', 'completion_year', 'parent_name', 'parent_phone', 'student_phone',
    'address', 'status',
])]
class Student extends Model
{
    use BelongsToSchool, HasFactory, IsPerson, SoftDeletes;

    public const STATUSES = ['active', 'inactive', 'graduated', 'transferred', 'suspended'];

    public const GENDERS = ['male', 'female'];

    /**
     * Used for students who have no admission number. Admission numbers are not
     * unique, so many students can share it; it never matches existing records on import.
     */
    public const NO_ADMISSION_NUMBER = '11111';

    /** Suggested levels; the field is free text so other school systems still fit. */
    public const LEVELS = ['O-Level', 'A-Level'];

    /** Benjamin Mkapa O-Level students have no individual class; the ID card shows the whole range. */
    public const O_LEVEL_CLASS = 'Form I - IV';

    public const A_LEVEL_CLASSES = ['Form V', 'Form VI'];

    public const A_LEVEL_COMBINATIONS = ['PCM', 'PCB', 'PGM', 'CBG', 'CBA', 'EGM', 'ECA', 'HGL', 'HGK', 'HGE', 'HKL'];

    /** Typical programme length in years, used when only the completion year is known. */
    public const LEVEL_YEARS = ['O-Level' => 4, 'A-Level' => 2];

    protected function casts(): array
    {
        return ['date_of_birth' => 'date', 'id_taken_at' => 'datetime'];
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /** The user who marked the printed ID card as collected. */
    public function idTakenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_taken_by');
    }

    public function hasTakenId(): bool
    {
        return $this->id_taken_at !== null;
    }

    /**
     * Mark (or undo) that the student's printed ID card has been collected.
     * A collected card has been printed, so active cards not yet printed in the system count as printed.
     */
    public function markIdTaken(bool $taken, ?int $userId): void
    {
        $this->forceFill([
            'id_taken_at' => $taken ? ($this->id_taken_at ?? now()) : null,
            'id_taken_by' => $taken ? ($this->id_taken_by ?? $userId) : null,
        ])->save();

        if ($taken) {
            $this->idCards()->where('status', 'active')->where('print_count', 0)
                ->update(['print_count' => 1, 'last_printed_at' => now()]);
        }
    }

    /** Students whose ID card has been printed: collected, or an active card printed from the system. */
    public function scopeIdPrinted(Builder $query): Builder
    {
        return $query->where(fn (Builder $q) => $q->whereNotNull('id_taken_at')
            ->orWhereHas('idCards', fn (Builder $card) => $card->where('status', 'active')->where('print_count', '>', 0)));
    }

    public function idCards(): MorphMany
    {
        return $this->morphMany(IdCard::class, 'holder');
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /** "2024 - 2026" from entry/completion years (falls back to the academic year name). */
    public function yearRange(): string
    {
        $entry = $this->entry_year;
        if (! $entry && $this->completion_year && isset(self::LEVEL_YEARS[$this->level])) {
            $entry = $this->completion_year - self::LEVEL_YEARS[$this->level];
        }

        return match (true) {
            $entry && $this->completion_year => "{$entry} - {$this->completion_year}",
            (bool) $this->completion_year => (string) $this->completion_year,
            (bool) $entry => (string) $entry,
            default => (string) $this->academicYear?->name,
        };
    }

    /** "Form I - IV" -> "Form\nI - IV": the first word on its own line (used by the {class_stacked} placeholder). */
    public function stackedClassLabel(): string
    {
        return preg_replace('/\s+/', "\n", trim((string) $this->class_name), 1);
    }

    public function classLabel(): string
    {
        return trim($this->class_name.' '.$this->stream);
    }

    /**
     * Filters shared by the student list, the generate wizards and exports.
     *
     * @param  array<string, mixed>  $filters
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['search'] ?? null, fn ($q, $term) => $this->applyPersonSearch($q, $term, 'admission_number'))
            ->when($filters['class_name'] ?? null, fn ($q, $v) => $q->where('class_name', $v))
            ->when($filters['stream'] ?? null, fn ($q, $v) => $q->where('stream', $v))
            ->when($filters['level'] ?? null, fn ($q, $v) => $q->where('level', $v))
            ->when($filters['gender'] ?? null, fn ($q, $v) => $q->where('gender', $v))
            ->when($filters['academic_year_id'] ?? null, fn ($q, $v) => $q->where('academic_year_id', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $v === 'deleted' ? $q->onlyTrashed() : $q->where('status', $v))
            ->when($filters['id_status'] ?? null, fn ($q, $v) => match ($v) {
                'waiting' => $q->whereNull('id_taken_at'),
                'taken' => $q->whereNotNull('id_taken_at'),
                default => $q,
            });
    }
}
