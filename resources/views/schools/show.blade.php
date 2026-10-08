@extends('layouts.app')
@section('title', $school->name)
@section('header')
    <div class="flex items-center gap-3">
        @if ($school->logoUrl())
            <img src="{{ $school->logoUrl() }}" alt="" class="size-14 object-contain">
        @endif
        <div>
            <h1 class="page-title">{{ $school->name }}</h1>
            <p class="page-subtitle">{{ $school->school_code }} · {{ $school->region }} <x-status :value="$school->status" class="ml-1"/></p>
        </div>
    </div>
@endsection
@section('actions')
    <form method="POST" action="{{ route('context.switch') }}">
        @csrf
        <input type="hidden" name="school_id" value="{{ $school->id }}">
        <input type="hidden" name="return" value="{{ route('dashboard') }}">
        <button class="btn btn-primary">Work in this school</button>
    </form>
    @can('schools.manage')<a href="{{ route('schools.edit', $school) }}" class="btn btn-secondary"><x-icon name="edit"/> Edit</a>@endcan
@endsection

@section('content')
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        @foreach (['Students' => $school->students_count, 'Staff' => $school->staff_count, 'Users' => $school->users_count,
                   'IDs printed' => $counts['ids_printed'], 'ID cards generated' => $counts['id_cards'], 'Certificates' => $counts['certificates'], 'Print jobs' => $counts['print_jobs']] as $label => $value)
            <div class="stat"><div class="stat-label">{{ $label }}</div><div class="stat-value">{{ number_format($value) }}</div></div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Profile</h2></div>
            <dl class="card-body">
                @foreach (['Short name' => $school->short_name, 'Registration no.' => $school->registration_number, 'Principal' => $school->principal_name,
                           'Address' => $school->address, 'District / ward' => trim($school->district.' '.$school->ward), 'Phone' => $school->phone,
                           'Email' => $school->email, 'Website' => $school->website] as $label => $value)
                    <div class="dl-row"><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                @endforeach
                <div class="dl-row"><dt>Brand colors</dt><dd class="flex gap-2">
                    <span class="inline-flex items-center gap-1"><span class="size-4 rounded" style="background: {{ $school->primary_color }}"></span>{{ $school->primary_color }}</span>
                    <span class="inline-flex items-center gap-1"><span class="size-4 rounded" style="background: {{ $school->secondary_color }}"></span>{{ $school->secondary_color }}</span>
                </dd></div>
                <div class="dl-row"><dt>Student IDs</dt><dd><code>{{ $school->student_id_format }}</code></dd></div>
                <div class="dl-row"><dt>Staff IDs</dt><dd><code>{{ $school->staff_id_format }}</code></dd></div>
                <div class="dl-row"><dt>Certificates</dt><dd><code>{{ $school->certificate_number_format }}</code></dd></div>
            </dl>
        </div>

        <div class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Users</h2>
                @can('users.manage')<a href="{{ route('users.create') }}" class="btn btn-secondary btn-sm"><x-icon name="plus"/> Add user</a>@endcan
            </div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last sign-in</th></tr></thead>
                    <tbody>
                    @forelse ($admins as $admin)
                        <tr>
                            <td class="font-medium">{{ $admin->name }}</td>
                            <td>{{ $admin->email }}</td>
                            <td><span class="badge badge-blue">{{ $admin->role->name }}</span></td>
                            <td><x-status :value="$admin->status"/></td>
                            <td class="text-slate-500">{{ $admin->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty icon="user" title="No users for this school yet"/></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="card-header border-t border-b-0"><h2 class="card-title">Academic years</h2></div>
            <div class="card-body flex flex-wrap gap-2">
                @foreach ($years as $year)
                    <span class="badge {{ $year->is_current ? 'badge-green' : 'badge-slate' }}">{{ $year->name }}{{ $year->is_current ? ' (current)' : '' }}</span>
                @endforeach
            </div>
        </div>
    </div>
@endsection
