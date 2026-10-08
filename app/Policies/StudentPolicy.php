<?php

namespace App\Policies;

use App\Models\Student;
use App\Models\User;

class StudentPolicy extends SchoolResourcePolicy
{
    protected string $prefix = 'students';

    /** Mark a printed ID card as collected: whoever edits students or operates printing. */
    public function markIdTaken(User $user, Student $student): bool
    {
        return $this->owns($user, $student)
            && ($user->hasPermission('students.update') || $user->hasPermission('print.execute'));
    }
}
