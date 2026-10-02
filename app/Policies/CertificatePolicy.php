<?php

namespace App\Policies;

use App\Models\Certificate;
use App\Models\User;

class CertificatePolicy extends SchoolResourcePolicy
{
    protected string $prefix = 'certificates';

    protected string $createAbility = 'generate';

    public function revoke(User $user, Certificate $certificate): bool
    {
        return $this->owns($user, $certificate) && $user->hasPermission('certificates.revoke');
    }
}
