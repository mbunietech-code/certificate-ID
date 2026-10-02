@extends('layouts.app')
@section('title', 'Users')
@section('header')
    <h1 class="page-title">Users</h1>
    <p class="page-subtitle">{{ $users->total() }} user(s){{ tenant()->school() ? ' in '.tenant()->school()->name : '' }}</p>
@endsection
@section('actions')
    @can('users.manage')<a href="{{ route('users.create') }}" class="btn btn-primary"><x-icon name="plus"/> Add user</a>@endcan
@endsection

@section('content')
    <div class="card">
        <form method="GET" class="card-header">
            <div class="flex flex-wrap items-center gap-2">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Name or email" class="form-input w-56">
                <select name="role_id" class="form-input w-40" data-autosubmit>
                    <option value="">All roles</option>
                    @foreach ($roles as $r)<option value="{{ $r->id }}" @selected(request('role_id') == $r->id)>{{ $r->name }}</option>@endforeach
                </select>
                <select name="status" class="form-input w-32" data-autosubmit>
                    <option value="">All statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <button class="btn btn-secondary"><x-icon name="search"/> Filter</button>
            </div>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>School</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
                <tbody>
                @forelse ($users as $u)
                    <tr>
                        <td class="font-medium">{{ $u->name }}</td>
                        <td>{{ $u->email }}</td>
                        <td><span class="badge {{ $u->role?->slug === 'super_admin' ? 'badge-violet' : 'badge-blue' }}">{{ $u->role?->name }}</span></td>
                        <td>{{ $u->school?->school_code ?? 'All schools' }}</td>
                        <td><x-status :value="$u->status"/></td>
                        <td class="text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $u)<a href="{{ route('users.edit', $u) }}" class="btn btn-ghost btn-sm"><x-icon name="edit"/></a>@endcan
                            @can('delete', $u)
                                <form method="POST" action="{{ route('users.destroy', $u) }}" class="inline" data-confirm="Delete user {{ $u->email }}?">@csrf @method('DELETE')
                                    <button class="btn btn-ghost btn-sm text-red-600"><x-icon name="trash"/></button></form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="user" title="No users found"/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $users->links() }}</div>
    </div>
@endsection
