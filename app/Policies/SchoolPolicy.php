<?php

namespace App\Policies;

use App\Models\School;
use App\Models\User;

/** Only the super admin manages schools; school admins may edit their own profile/branding. */
class SchoolPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('schools.view');
    }

    public function view(User $user, School $school): bool
    {
        return $user->canAccessSchool($school->id);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('schools.manage');
    }

    public function update(User $user, School $school): bool
    {
        return $user->hasPermission('schools.manage');
    }

    public function updateProfile(User $user, School $school): bool
    {
        return $user->canAccessSchool($school->id) && $user->hasPermission('settings.school');
    }
}
