<?php

namespace App\Policies;

class StaffPolicy extends SchoolResourcePolicy
{
    protected string $prefix = 'staff';

    protected string $createAbility = 'manage';

    protected string $updateAbility = 'manage';

    protected string $deleteAbility = 'manage';
}
