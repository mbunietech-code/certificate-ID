<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Created only by App\Services\Printing\PrintJobService. */
class PrintJob extends Model
{
    use BelongsToSchool;

    protected $guarded = ['*'];

    public const TYPE_STUDENT_ID = 'student_id';

    public const TYPE_STAFF_ID = 'staff_id';

    public const TYPE_CERTIFICATE = 'certificate';

    public const TYPES = [
        self::TYPE_STUDENT_ID => 'Student ID',
        self::TYPE_STAFF_ID => 'Staff ID',
        self::TYPE_CERTIFICATE => 'Certificate',
    ];

    public const STATUSES = ['pending', 'processing', 'completed', 'failed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_printed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrintJobItem::class)->orderBy('position');
    }

    public function isIdCardJob(): bool
    {
        return $this->type !== self::TYPE_CERTIFICATE;
    }

    public function template(): IdCardTemplate|CertificateTemplate|null
    {
        $class = $this->isIdCardJob() ? IdCardTemplate::class : CertificateTemplate::class;

        return $class::withTrashed()->withoutGlobalScope('school')->find($this->template_id);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'cancelled'], true);
    }

    public function progressPercent(): int
    {
        return $this->total_items > 0 ? (int) floor(($this->completed_items + $this->failed_items) / $this->total_items * 100) : 0;
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return data_get($this->options, $key, $default);
    }

    /** @param  array<string, mixed>  $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['school_id'] ?? null, fn ($q, $v) => $q->where('school_id', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['type'] ?? null, fn ($q, $v) => $q->where('type', $v))
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['job_number'] ?? null, fn ($q, $v) => $q->where('job_number', 'like', "%{$v}%"))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'));
    }
}
