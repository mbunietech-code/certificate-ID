@extends('layouts.app')
@section('title', 'Dashboard')
@section('header')
    <h1 class="page-title">Dashboard</h1>
    <p class="page-subtitle">{{ $school ? $school->name : 'Overview of all schools' }}</p>
@endsection
@section('actions')
    @can('id_cards.generate')
        <a href="{{ route('id-cards.generate') }}" class="btn btn-primary"><x-icon name="id-card"/> Generate IDs</a>
    @endcan
    @can('certificates.generate')
        <a href="{{ route('certificates.generate') }}" class="btn btn-secondary"><x-icon name="certificate"/> Generate certificates</a>
    @endcan
@endsection

@section('content')
    @php
        $cards = $school
            ? ['Total students' => $stats['students'], 'Total staff' => $stats['staff'], 'IDs generated' => $stats['id_cards'],
               'Certificates generated' => $stats['certificates'], 'Pending print jobs' => $stats['pending_jobs'], 'Completed print jobs' => $stats['completed_jobs']]
            : ['Total schools' => $stats['schools'], 'Active schools' => $stats['active_schools'], 'Total students' => $stats['students'],
               'Total staff' => $stats['staff'], 'IDs generated' => $stats['id_cards'], 'Certificates' => $stats['certificates'], 'Print jobs' => $stats['print_jobs']];
        $maxDay = max(1, collect($activity)->max(fn ($d) => $d['ids'] + $d['certificates']));
    @endphp

    <div class="grid grid-cols-2 gap-3 md:grid-cols-3 {{ count($cards) > 6 ? 'xl:grid-cols-7' : 'xl:grid-cols-6' }}">
        @foreach ($cards as $label => $value)
            <div class="stat">
                <div class="stat-label">{{ $label }}</div>
                <div class="stat-value">{{ number_format($value) }}</div>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header">
                <h2 class="card-title">Documents generated – last 14 days</h2>
                <div class="flex items-center gap-3 text-xs text-slate-500">
                    <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded-sm bg-blue-600"></span> ID cards</span>
                    <span class="inline-flex items-center gap-1"><span class="size-2.5 rounded-sm bg-amber-500"></span> Certificates</span>
                </div>
            </div>
            <div class="card-body">
                <div class="flex h-44 items-end gap-1.5">
                    @foreach ($activity as $day => $counts)
                        @php $total = $counts['ids'] + $counts['certificates']; @endphp
                        <div class="group flex h-full flex-1 flex-col items-center justify-end" title="{{ \Illuminate\Support\Carbon::parse($day)->format('d M') }}: {{ $counts['ids'] }} IDs, {{ $counts['certificates'] }} certificates">
                            <span class="mb-1 text-[10px] text-slate-500 opacity-0 group-hover:opacity-100">{{ $total }}</span>
                            <div class="flex w-full flex-col justify-end overflow-hidden rounded-t" style="height: {{ max(2, $total / $maxDay * 100) }}%">
                                <div class="bg-amber-500" style="height: {{ $total ? $counts['certificates'] / $total * 100 : 0 }}%"></div>
                                <div class="flex-1 {{ $total ? 'bg-blue-600' : 'bg-slate-200' }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-1 flex gap-1.5 text-[10px] text-slate-400">
                    @foreach ($activity as $day => $counts)
                        <div class="flex-1 text-center">{{ \Illuminate\Support\Carbon::parse($day)->format('d') }}</div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">{{ $school ? 'Active students by class' : 'Schools' }}</h2>
                @if (! $school) @can('schools.view')<a href="{{ route('schools.index') }}" class="text-xs font-medium text-blue-700 hover:underline">View all</a>@endcan @endif
            </div>
            <div class="card-body space-y-2">
                @if ($school)
                    @php $maxClass = max(1, $byClass->max()); @endphp
                    @forelse ($byClass as $class => $total)
                        <div>
                            <div class="flex justify-between text-xs"><span class="font-medium text-slate-700">{{ $class }}</span><span class="tabular-nums text-slate-500">{{ $total }}</span></div>
                            <div class="mt-0.5 h-2 rounded bg-slate-100"><div class="h-2 rounded" style="width: {{ $total / $maxClass * 100 }}%; background: {{ $school->primary_color }}"></div></div>
                        </div>
                    @empty
                        <x-empty icon="students" title="No students yet"/>
                    @endforelse
                @else
                    @forelse ($schools as $s)
                        <div class="flex items-center justify-between gap-2 border-b border-slate-100 pb-2 text-sm last:border-0">
                            <span class="min-w-0 truncate"><span class="font-medium text-slate-800">{{ $s->school_code }}</span> <span class="text-slate-500">{{ $s->name }}</span></span>
                            <span class="shrink-0 text-xs text-slate-500 tabular-nums">{{ number_format($s->students_count) }} students · {{ $s->staff_count }} staff</span>
                        </div>
                    @empty
                        <x-empty icon="school" title="No schools yet"/>
                    @endforelse
                @endif
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header">
            <h2 class="card-title">Recent print jobs</h2>
            @can('print.view')<a href="{{ route('print.history') }}" class="text-xs font-medium text-blue-700 hover:underline">Print history</a>@endcan
        </div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Job</th>@if (! $school)<th>School</th>@endif<th>Type</th><th>Template</th><th>Items</th><th>Status</th><th>By</th><th>Date</th></tr></thead>
                <tbody>
                @forelse ($recentJobs as $job)
                    <tr>
                        <td>@can('view', $job)<a href="{{ route('print.show', $job) }}" class="font-medium text-blue-700 hover:underline">{{ $job->job_number }}</a>@else {{ $job->job_number }} @endcan</td>
                        @if (! $school)<td>{{ $job->school->school_code }}</td>@endif
                        <td>{{ $job->typeLabel() }}</td>
                        <td class="max-w-56 truncate">{{ $job->template_name }}</td>
                        <td class="tabular-nums">{{ $job->completed_items }}/{{ $job->total_items }}</td>
                        <td><x-status :value="$job->status"/></td>
                        <td>{{ $job->user?->name ?? '—' }}</td>
                        <td class="whitespace-nowrap text-slate-500">{{ $job->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8"><x-empty icon="printer" title="No print jobs yet">Generate ID cards or certificates to create the first print job.</x-empty></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
