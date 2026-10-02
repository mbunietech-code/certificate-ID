<?php

namespace App\Policies;

use App\Models\User;

/**
 * School admins manage users of their own school only and can never create
 * or edit super admins (super admin passes via Gate::before).
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('users.view');
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission('users.view') && $this->sameSchool($user, $target);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('users.manage');
    }

    public function update(User $user, User $target): bool
    {
        return $user->hasPermission('users.manage') && $this->sameSchool($user, $target) && ! $target->isSuperAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $this->update($user, $target) && $user->id !== $target->id;
    }

    private function sameSchool(User $user, User $target): bool
    {
        return $target->school_id !== null && $user->canAccessSchool((int) $target->school_id);
    }
}
