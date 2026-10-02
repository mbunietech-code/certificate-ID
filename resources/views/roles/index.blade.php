@extends('layouts.app')
@section('title', 'Roles & permissions')
@section('header')
    <h1 class="page-title">Roles &amp; permissions</h1>
    <p class="page-subtitle">Tick what each role may do. Super Admin always has every permission; school/system administration permissions are reserved for it.</p>
@endsection

@section('content')
    @php $reserved = config('access.super_admin_only'); @endphp
    <div class="grid gap-4 xl:grid-cols-2">
        @foreach ($roles as $role)
            <form method="POST" action="{{ route('roles.update', $role) }}" class="card">
                @csrf @method('PUT')
                <div class="card-header">
                    <div>
                        <h2 class="card-title">{{ $role->name }} <span class="ml-1 font-mono text-xs font-normal text-slate-400">{{ strtoupper($role->slug) }}</span></h2>
                        <p class="text-xs text-slate-500">{{ $role->description }} · {{ $role->users_count }} user(s)</p>
                    </div>
                    @unless ($role->isSuperAdmin())<button class="btn btn-primary btn-sm">Save</button>@endunless
                </div>
                <div class="card-body grid gap-3 sm:grid-cols-2">
                    @php $granted = $role->permissions->pluck('id')->all(); @endphp
                    @foreach ($groups as $group => $permissions)
                        <fieldset>
                            <legend class="mb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">{{ $group }}</legend>
                            @foreach ($permissions as $permission)
                                @php $locked = $role->isSuperAdmin() || in_array($permission->slug, $reserved, true); @endphp
                                <label class="flex items-start gap-2 py-0.5 text-sm {{ $locked && ! $role->isSuperAdmin() ? 'opacity-50' : '' }}" title="{{ $permission->slug }}">
                                    <input type="checkbox" name="permissions[]" value="{{ $permission->id }}" class="form-check mt-0.5"
                                        @checked($role->isSuperAdmin() || in_array($permission->id, $granted, true)) @disabled($locked)>
                                    {{ $permission->name }}
                                </label>
                            @endforeach
                        </fieldset>
                    @endforeach
                </div>
            </form>
        @endforeach
    </div>
@endsection
