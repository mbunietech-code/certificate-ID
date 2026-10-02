<?php

namespace App\Models;

use App\Support\Tenancy\BelongsToSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * An issued ID card. Created only by App\Services\Documents\IdCardIssuer,
 * so attributes are force-filled there and nothing is mass assignable.
 */
class IdCard extends Model
{
    use BelongsToSchool;

    protected $guarded = ['*'];

    public const STATUSES = ['active', 'replaced', 'revoked', 'expired'];

    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'expires_at' => 'date',
            'last_printed_at' => 'datetime',
        ];
    }

    public function holder(): MorphTo
    {
        return $this->morphTo()->withTrashed();
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(IdCardTemplate::class, 'template_id')->withTrashed();
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function isValid(): bool
    {
        return $this->status === 'active' && (! $this->expires_at || $this->expires_at->endOfDay()->isFuture());
    }

    public function holderKind(): string
    {
        return $this->holder_type === 'staff' ? 'Staff' : 'Student';
    }

    public function verificationUrl(): string
    {
        return verification_url('id', $this->verification_code);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }
}
