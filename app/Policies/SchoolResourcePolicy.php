<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Base policy for school-owned records: the user needs the permission AND
 * the record must belong to the user's school. This is a second line of
 * defence behind the tenant global scope (super admin passes via Gate::before).
 */
abstract class SchoolResourcePolicy
{
    /** Permission prefix, e.g. "students". */
    protected string $prefix;

    protected string $createAbility = 'create';

    protected string $updateAbility = 'update';

    protected string $deleteAbility = 'delete';

    public function viewAny(User $user): bool
    {
        return $user->hasPermission("{$this->prefix}.view");
    }

    public function view(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $user->hasPermission("{$this->prefix}.view");
    }

    public function create(User $user): bool
    {
        return $user->hasPermission("{$this->prefix}.{$this->createAbility}");
    }

    public function update(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $user->hasPermission("{$this->prefix}.{$this->updateAbility}");
    }

    public function delete(User $user, Model $model): bool
    {
        return $this->owns($user, $model) && $user->hasPermission("{$this->prefix}.{$this->deleteAbility}");
    }

    protected function owns(User $user, Model $model): bool
    {
        return $user->canAccessSchool($model->getAttribute('school_id') === null ? null : (int) $model->getAttribute('school_id'));
    }
}
