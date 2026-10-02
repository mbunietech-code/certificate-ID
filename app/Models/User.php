<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'status'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === Role::SUPER_ADMIN;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasPermission(string $slug): bool
    {
        if (! $this->role) {
            return false;
        }

        return $this->isSuperAdmin() || in_array($slug, $this->role->permissionSlugs(), true);
    }

    /** Whether this user may touch records of the given school. */
    public function canAccessSchool(?int $schoolId): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $schoolId !== null && $this->school_id !== null && $schoolId === (int) $this->school_id;
    }

    /** Users visible to the given viewer: super admin sees all, others only their school. */
    public function scopeVisibleTo(Builder $query, User $viewer, ?int $schoolFilter = null): Builder
    {
        if ($viewer->isSuperAdmin()) {
            return $schoolFilter ? $query->where('school_id', $schoolFilter) : $query;
        }

        return $query->where('school_id', $viewer->school_id ?? 0);
    }
}
