<?php

namespace App\Support\Tenancy;

use App\Models\School;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Resolves which school the current request/job is allowed to see.
 *
 * - School users are always locked to their own school_id (never from input).
 * - The super admin works across all schools, optionally narrowed to the
 *   school picked in the header selector (stored in the session).
 * - Queue jobs and console commands have no user; they must call runFor().
 */
class TenantContext
{
    public const SESSION_KEY = 'active_school_id';

    private ?int $forcedSchoolId = null;

    private bool $bypass = false;

    private ?School $cachedSchool = null;

    public function user(): ?User
    {
        /** @var User|null $user */
        $user = Auth::guard('web')->user();

        return $user;
    }

    /**
     * School id that every tenant-scoped query must be filtered by,
     * or null when the caller may see all schools.
     */
    public function scopedSchoolId(): ?int
    {
        if ($this->bypass) {
            return null;
        }

        if ($this->forcedSchoolId !== null) {
            return $this->forcedSchoolId;
        }

        $user = $this->user();

        if (! $user) {
            return null;
        }

        if ($user->isSuperAdmin()) {
            return $this->superAdminSelection();
        }

        // Fail closed: a school user without a school sees nothing.
        return $user->school_id ?? 0;
    }

    /**
     * School id to stamp on newly created records. Throws when the caller
     * has no single school selected (super admin in "All schools" mode).
     */
    public function schoolIdForWrite(): int
    {
        $id = $this->scopedSchoolId();

        if (! $id) {
            throw new RuntimeException('No school selected. Select a school before creating school records.');
        }

        return $id;
    }

    public function hasSchool(): bool
    {
        return (bool) $this->scopedSchoolId();
    }

    public function school(): ?School
    {
        $id = $this->scopedSchoolId();

        if (! $id) {
            return null;
        }

        if ($this->cachedSchool?->id !== $id) {
            $this->cachedSchool = School::find($id);
        }

        return $this->cachedSchool;
    }

    public function isAllSchools(): bool
    {
        return $this->scopedSchoolId() === null;
    }

    public function selectSchool(?int $schoolId): void
    {
        if ($schoolId) {
            session([self::SESSION_KEY => $schoolId]);
        } else {
            session()->forget(self::SESSION_KEY);
        }
        $this->cachedSchool = null;
    }

    /** Run a callback scoped to one school (used by queue jobs). */
    public function runFor(int $schoolId, Closure $callback): mixed
    {
        $previous = $this->forcedSchoolId;
        $this->forcedSchoolId = $schoolId;

        try {
            return $callback();
        } finally {
            $this->forcedSchoolId = $previous;
        }
    }

    /** Run a callback without tenant filtering (public verification, system tasks). */
    public function withoutScope(Closure $callback): mixed
    {
        $previous = $this->bypass;
        $this->bypass = true;

        try {
            return $callback();
        } finally {
            $this->bypass = $previous;
        }
    }

    private function superAdminSelection(): ?int
    {
        $request = request();

        if (! $request->hasSession()) {
            return null;
        }

        $id = (int) $request->session()->get(self::SESSION_KEY);

        return $id > 0 ? $id : null;
    }
}
