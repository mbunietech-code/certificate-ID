<?php

namespace App\Policies;

class AcademicYearPolicy extends SchoolResourcePolicy
{
    protected string $prefix = 'academic_years';

    protected string $createAbility = 'manage';

    protected string $updateAbility = 'manage';

    protected string $deleteAbility = 'manage';
}
