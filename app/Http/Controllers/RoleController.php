<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Role → permission matrix (super admin only). */
class RoleController extends Controller
{
    public function index(): View
    {
        $this->authorize('roles.manage');

        return view('roles.index', [
            'roles' => Role::with('permissions:id')->withCount('users')->orderBy('id')->get(),
            'groups' => Permission::orderBy('id')->get()->groupBy('group_name'),
        ]);
    }

    public function update(Request $request, Role $role): RedirectResponse
    {
        $this->authorize('roles.manage');
        abort_if($role->isSuperAdmin(), 422, 'The super admin role always has every permission.');

        $data = $request->validate(['permissions' => ['nullable', 'array'], 'permissions.*' => ['integer', 'exists:permissions,id']]);
        $ids = Permission::whereIn('id', $data['permissions'] ?? [])
            ->whereNotIn('slug', config('access.super_admin_only'))
            ->pluck('id');

        $role->permissions()->sync($ids);
        Role::flushPermissionCache($role->id);
        $this->audit('role.permissions_updated', $role, "Updated permissions of role {$role->name}", ['count' => $ids->count()]);

        return back()->with('success', "Permissions of {$role->name} saved.");
    }
}
