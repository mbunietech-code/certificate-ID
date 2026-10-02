<?php

namespace App\Policies;

use App\Models\PrintJob;
use App\Models\User;

class PrintJobPolicy extends SchoolResourcePolicy
{
    protected string $prefix = 'print';

    /** Print, download or retry a job. */
    public function print(User $user, PrintJob $job): bool
    {
        return $this->owns($user, $job) && $user->hasPermission('print.execute');
    }
}
