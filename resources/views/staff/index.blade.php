@extends('layouts.app')
@section('title', 'Staff')
@section('header')
    <h1 class="page-title">Staff</h1>
    <p class="page-subtitle">{{ number_format($staff->total()) }} staff member(s)</p>
@endsection
@section('actions')
    <a href="{{ route('staff.export', request()->query() + ['format' => 'xlsx']) }}" class="btn btn-secondary"><x-icon name="download"/> Export</a>
    @can('id_cards.generate')<a href="{{ route('id-cards.generate', ['holder' => 'staff']) }}" class="btn btn-secondary"><x-icon name="id-card"/> Staff IDs</a>@endcan
    @can('staff.manage')<a href="{{ route('staff.create') }}" class="btn btn-primary"><x-icon name="plus"/> Add staff</a>@endcan
@endsection

@section('content')
    <div class="card">
        <form method="GET" class="card-header">
            <div class="flex flex-wrap items-center gap-2">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or employee no." class="form-input w-56">
                <select name="department" class="form-input w-40" data-autosubmit>
                    <option value="">All departments</option>
                    @foreach ($departments as $d)<option @selected(($filters['department'] ?? '') === $d)>{{ $d }}</option>@endforeach
                </select>
                <select name="employment_status" class="form-input w-36" data-autosubmit>
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Staff::STATUSES as $st)<option value="{{ $st }}" @selected(($filters['employment_status'] ?? '') === $st)>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>@endforeach
                </select>
                <button class="btn btn-secondary"><x-icon name="search"/> Filter</button>
            </div>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Name</th><th>Employee no.</th>@if (tenant()->isAllSchools())<th>School</th>@endif<th>Job title</th><th>Department</th><th>Phone</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($staff as $member)
                    <tr>
                        <td>
                            <div class="flex items-center gap-2.5">
                                @if ($member->photoUrl())
                                    <img src="{{ $member->photoUrl() }}" alt="" class="size-8 rounded-full object-cover">
                                @else
                                    <span class="grid size-8 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">{{ $member->initials() }}</span>
                                @endif
                                <a href="{{ route('staff.show', $member) }}" class="font-medium text-slate-900 hover:text-blue-700">{{ $member->full_name }}</a>
                            </div>
                        </td>
                        <td>{{ $member->employee_number }}</td>
                        @if (tenant()->isAllSchools())<td>{{ $member->school->school_code }}</td>@endif
                        <td>{{ $member->job_title }}</td>
                        <td>{{ $member->department }}</td>
                        <td>{{ $member->phone }}</td>
                        <td><x-status :value="$member->employment_status"/></td>
                        <td class="text-right">@can('update', $member)<a href="{{ route('staff.edit', $member) }}" class="btn btn-ghost btn-sm"><x-icon name="edit"/></a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty icon="staff" title="No staff found"/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $staff->links() }}</div>
    </div>
@endsection
