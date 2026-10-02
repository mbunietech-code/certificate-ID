<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Creates/updates permissions and the five system roles from config/access.php.
 * Safe to re-run: existing roles keep any permissions the super admin changed
 * unless $resetAssignments is true.
 */
class RolePermissionSeeder extends Seeder
{
    public function __construct(private bool $resetAssignments = false) {}

    public function run(): void
    {
        foreach (config('access.permissions') as $group => $permissions) {
            foreach ($permissions as $slug => $name) {
                Permission::updateOrCreate(['slug' => $slug], ['name' => $name, 'group_name' => $group]);
            }
        }

        $all = Permission::pluck('id', 'slug');

        foreach (config('access.roles') as $slug => $definition) {
            $role = Role::firstOrNew(['slug' => $slug]);
            $isNew = ! $role->exists;
            $role->fill(['name' => $definition['name'], 'description' => $definition['description'], 'is_system' => true])->save();

            if ($isNew || $this->resetAssignments) {
                $ids = collect($definition['permissions'])->flatMap(fn ($pattern) => $all->filter(fn ($id, $permission) => Str::is($pattern, $permission))->values());
                if ($slug !== Role::SUPER_ADMIN) {
                    $ids = $ids->diff($all->only(config('access.super_admin_only'))->values());
                }
                $role->permissions()->sync($ids->unique()->values());
            }
            Role::flushPermissionCache($role->id);
        }
    }
}
