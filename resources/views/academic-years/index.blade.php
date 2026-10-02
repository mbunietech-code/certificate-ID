@extends('layouts.app')
@section('title', 'Academic years')
@section('header')
    <h1 class="page-title">Academic years</h1>
    <p class="page-subtitle">The current year is used by default for new students, ID cards and certificates.</p>
@endsection
@section('actions')
    @can('academic_years.manage')<a href="{{ route('academic-years.create') }}" class="btn btn-primary"><x-icon name="plus"/> Add academic year</a>@endcan
@endsection

@section('content')
    <div class="card">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr>@if (tenant()->isAllSchools())<th>School</th>@endif<th>Year</th><th>Start</th><th>End</th><th class="text-right">Students</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($years as $year)
                    <tr>
                        @if (tenant()->isAllSchools())<td>{{ $year->school->school_code }}</td>@endif
                        <td class="font-medium">{{ $year->name }} @if ($year->is_current)<span class="badge badge-green ml-1">Current</span>@endif</td>
                        <td>{{ format_date($year->start_date) }}</td>
                        <td>{{ format_date($year->end_date) }}</td>
                        <td class="text-right tabular-nums">{{ number_format($year->students_count) }}</td>
                        <td><x-status :value="$year->status"/></td>
                        <td class="text-right whitespace-nowrap">
                            @can('update', $year)
                                @unless ($year->is_current)
                                    <form method="POST" action="{{ route('academic-years.current', $year) }}" class="inline">
                                        @csrf <button class="btn btn-ghost btn-sm">Set current</button>
                                    </form>
                                @endunless
                                <a href="{{ route('academic-years.edit', $year) }}" class="btn btn-ghost btn-sm"><x-icon name="edit"/></a>
                                <form method="POST" action="{{ route('academic-years.destroy', $year) }}" class="inline" data-confirm="Delete academic year {{ $year->name }}?">
                                    @csrf @method('DELETE') <button class="btn btn-ghost btn-sm text-red-600"><x-icon name="trash"/></button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="calendar" title="No academic years yet"/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $years->links() }}</div>
    </div>
@endsection
