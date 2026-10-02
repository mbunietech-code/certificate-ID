@extends('layouts.app')
@section('title', 'Schools')
@section('header')
    <h1 class="page-title">Schools</h1>
    <p class="page-subtitle">{{ $schools->total() }} school(s) managed in this system</p>
@endsection
@section('actions')
    @can('schools.manage')<a href="{{ route('schools.create') }}" class="btn btn-primary"><x-icon name="plus"/> Add school</a>@endcan
@endsection

@section('content')
    <div class="card">
        <form method="GET" class="card-header">
            <div class="flex flex-wrap items-center gap-2">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Search name, code, region…" class="form-input w-64">
                <select name="status" class="form-input w-36" data-autosubmit>
                    <option value="">All statuses</option>
                    <option value="active" @selected(request('status') === 'active')>Active</option>
                    <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
                </select>
                <button class="btn btn-secondary"><x-icon name="search"/> Search</button>
            </div>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>School</th><th>Code</th><th>Region</th><th class="text-right">Students</th><th class="text-right">Staff</th><th class="text-right">Users</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($schools as $school)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2.5">
                                @if ($school->logoUrl())
                                    <img src="{{ $school->logoUrl() }}" alt="" class="size-8 object-contain">
                                @else
                                    <span class="size-8 rounded-full" style="background: {{ $school->primary_color }}"></span>
                                @endif
                                <a href="{{ route('schools.show', $school) }}" class="font-medium text-slate-900 hover:text-blue-700">{{ $school->name }}</a>
                            </div>
                        </td>
                        <td><span class="badge badge-slate">{{ $school->school_code }}</span></td>
                        <td>{{ $school->region }}</td>
                        <td class="text-right tabular-nums">{{ number_format($school->students_count) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($school->staff_count) }}</td>
                        <td class="text-right tabular-nums">{{ $school->users_count }}</td>
                        <td><x-status :value="$school->status"/></td>
                        <td class="text-right whitespace-nowrap">
                            <form method="POST" action="{{ route('context.switch') }}" class="inline">
                                @csrf
                                <input type="hidden" name="school_id" value="{{ $school->id }}">
                                <input type="hidden" name="return" value="{{ route('dashboard') }}">
                                <button class="btn btn-ghost btn-sm" title="Work in this school">Open</button>
                            </form>
                            @can('schools.manage')
                                <a href="{{ route('schools.edit', $school) }}" class="btn btn-ghost btn-sm"><x-icon name="edit"/></a>
                                <form method="POST" action="{{ route('schools.status', $school) }}" class="inline" data-confirm="{{ $school->isActive() ? 'Deactivate' : 'Activate' }} {{ $school->name }}? {{ $school->isActive() ? 'Its users will no longer be able to sign in.' : '' }}">
                                    @csrf @method('PATCH')
                                    <button class="btn btn-ghost btn-sm">{{ $school->isActive() ? 'Deactivate' : 'Activate' }}</button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty icon="school" title="No schools found"/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $schools->links() }}</div>
    </div>
@endsection
