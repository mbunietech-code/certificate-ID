<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only audit trail. Not tenant-scoped via the global scope because
 * login events have no school; AuditLogController filters explicitly.
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @param  array<string, mixed>  $filters */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['school_id'] ?? null, fn ($q, $v) => $q->where('school_id', $v))
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['action'] ?? null, fn ($q, $v) => $q->where('action', 'like', $v.'%'))
            ->when($filters['entity_type'] ?? null, fn ($q, $v) => $q->where('entity_type', $v))
            ->when($filters['date_from'] ?? null, fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when($filters['date_to'] ?? null, fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'));
    }
}
