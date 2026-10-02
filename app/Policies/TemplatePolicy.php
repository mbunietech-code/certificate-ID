<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ID card and certificate templates. Global templates (school_id NULL) are
 * visible to every school but only the super admin may change them.
 */
class TemplatePolicy extends SchoolResourcePolicy
{
    protected string $prefix = 'templates';

    protected string $createAbility = 'manage';

    protected string $updateAbility = 'manage';

    protected string $deleteAbility = 'manage';

    public function view(User $user, Model $model): bool
    {
        return $user->hasPermission('templates.view')
            && ($model->getAttribute('school_id') === null || $this->owns($user, $model));
    }

    /** Use a template to generate documents. */
    public function use(User $user, Model $model): bool
    {
        return ($model->getAttribute('school_id') === null || $this->owns($user, $model))
            && $model->getAttribute('status') === 'active';
    }

    /** Copy a (global) template into the user's school. */
    public function duplicate(User $user, Model $model): bool
    {
        return $this->view($user, $model) && $user->hasPermission('templates.manage');
    }
}
