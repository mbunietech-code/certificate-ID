<div class="card-body grid gap-4 sm:grid-cols-2">
    <x-form.input name="name" label="Full name" :value="$user->name" required/>
    <x-form.input name="email" type="email" label="Email (used to sign in)" :value="$user->email" required/>
    <x-form.input name="phone" label="Phone" :value="$user->phone"/>
    <x-form.select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive (cannot sign in)']" :value="$user->status ?? 'active'" required/>
    <x-form.select name="role_id" label="Role" :options="$roles->mapWithKeys(fn ($r) => [$r->id => $r->name])->all()" :value="$user->role_id" placeholder="Select a role…" required
        hint="School Admin: everything in the school · Registrar: students, staff, certificates · Printer: print center · Viewer: read-only."/>
    @if ($schools->isNotEmpty())
        <x-form.select name="school_id" label="School" :options="$schools->mapWithKeys(fn ($s) => [$s->id => $s->name])->all()" :value="$user->school_id" placeholder="— none (super admin only) —"
            hint="Required for every role except Super Admin."/>
    @endif
    <div class="border-t border-slate-100 pt-3 sm:col-span-2">
        <h2 class="card-title">{{ $user->exists ? 'Reset password' : 'Password' }}</h2>
        <p class="form-hint">{{ $user->exists ? 'Leave blank to keep the current password.' : '' }} At least 8 characters with letters and numbers.</p>
    </div>
    <x-form.input name="password" type="password" label="Password" :required="! $user->exists" autocomplete="new-password"/>
    <x-form.input name="password_confirmation" type="password" label="Confirm password" :required="! $user->exists" autocomplete="new-password"/>
</div>
