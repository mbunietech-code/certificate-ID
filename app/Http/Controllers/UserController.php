<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::visibleTo($request->user(), $this->tenant()->scopedSchoolId())
            ->with(['role:id,name,slug', 'school:id,school_code,name'])
            ->when($request->query('search'), fn ($q, $t) => $q->where(fn ($q) => $q->where('name', 'like', "%{$t}%")->orWhere('email', 'like', "%{$t}%")))
            ->when($request->query('role_id'), fn ($q, $r) => $q->where('role_id', $r))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(30)->withQueryString();

        return view('users.index', ['users' => $users, 'roles' => $this->assignableRoles($request->user())]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', User::class);

        $user = new User(['status' => 'active']);
        $user->school_id = $this->tenant()->scopedSchoolId();

        return view('users.create', $this->formData($request->user()) + compact('user'));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', User::class);

        $data = $this->validated($request);
        $user = new User(collect($data)->only(['name', 'email', 'phone', 'password', 'status'])->all());
        $this->applyRoleAndSchool($user, $request->user(), $data);
        $user->save();

        $this->audit('user.created', $user, "Created user {$user->email} ({$user->role->name})");

        return redirect()->route('users.index')->with('success', 'User created.');
    }

    public function edit(Request $request, User $user): View
    {
        $this->authorize('update', $user);

        return view('users.edit', $this->formData($request->user()) + compact('user'));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $this->authorize('update', $user);

        $data = $this->validated($request, $user);
        $user->fill(collect($data)->only(['name', 'email', 'phone', 'status'])->all());
        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }
        if ($user->id === $request->user()->id && $data['status'] !== 'active') {
            return back()->withErrors(['status' => 'You cannot deactivate your own account.']);
        }
        $this->applyRoleAndSchool($user, $request->user(), $data);
        $changes = array_keys($user->getDirty());
        $user->save();

        $this->audit('user.updated', $user, "Updated user {$user->email}", ['changed' => array_values(array_diff($changes, ['password']))]);

        return redirect()->route('users.index')->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        $this->authorize('delete', $user);

        $user->delete();
        $this->audit('user.deleted', null, "Deleted user {$user->email}", ['user_id' => $user->id], $user->school_id);

        return redirect()->route('users.index')->with('success', 'User deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?User $user = null): array
    {
        $actor = $request->user();

        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user?->id)],
            'phone' => ['nullable', 'string', 'max:40'],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)->letters()->numbers()],
            'role_id' => ['required', 'integer', Rule::in($this->assignableRoles($actor)->pluck('id'))],
            'school_id' => [$actor->isSuperAdmin() ? 'nullable' : 'prohibited', 'integer', 'exists:schools,id'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    /**
     * Role and school are never mass-assigned. School users can only place
     * users in their own school; only the super admin creates super admins.
     *
     * @param  array<string, mixed>  $data
     */
    private function applyRoleAndSchool(User $user, User $actor, array $data): void
    {
        $role = Role::findOrFail($data['role_id']);
        $user->role_id = $role->id;
        $user->setRelation('role', $role);

        if ($role->isSuperAdmin()) {
            $user->school_id = null;

            return;
        }

        $schoolId = $actor->isSuperAdmin() ? ($data['school_id'] ?? $this->tenant()->scopedSchoolId()) : $actor->school_id;
        if (! $schoolId) {
            throw ValidationException::withMessages(['school_id' => 'Choose the school this user belongs to.']);
        }
        $user->school_id = (int) $schoolId;
    }

    /** @return Collection<int, Role> */
    private function assignableRoles(User $actor): Collection
    {
        return Role::orderBy('id')->get()->reject(fn (Role $r) => $r->isSuperAdmin() && ! $actor->isSuperAdmin())->values();
    }

    /** @return array<string, mixed> */
    private function formData(User $actor): array
    {
        return [
            'roles' => $this->assignableRoles($actor),
            'schools' => $actor->isSuperAdmin() ? School::orderBy('name')->get(['id', 'name', 'school_code']) : collect(),
        ];
    }
}
