<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable(['name', 'slug', 'description', 'is_system'])]
class Role extends Model
{
    public const SUPER_ADMIN = 'super_admin';

    public const SCHOOL_ADMIN = 'school_admin';

    public const REGISTRAR = 'registrar';

    public const PRINTER = 'printer';

    public const VIEWER = 'viewer';

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === self::SUPER_ADMIN;
    }

    /** @return array<int, string> permission slugs, cached per role */
    public function permissionSlugs(): array
    {
        return Cache::rememberForever(self::cacheKey($this->id), fn () => $this->permissions()->pluck('slug')->all());
    }

    public static function flushPermissionCache(int $roleId): void
    {
        Cache::forget(self::cacheKey($roleId));
    }

    private static function cacheKey(int $roleId): string
    {
        return "role:{$roleId}:permissions";
    }
}
