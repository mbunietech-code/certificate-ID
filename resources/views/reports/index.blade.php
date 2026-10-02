@extends('layouts.app')
@section('title', 'Reports')
@section('header')
    <h1 class="page-title">Reports</h1>
    <p class="page-subtitle">{{ tenant()->school()?->name ?? 'All schools' }} · every report can be exported to CSV, Excel or PDF.</p>
@endsection

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($reports as $key => $report)
            <a href="{{ route('reports.show', $key) }}" class="card group p-4 hover:border-blue-400">
                <div class="flex items-center gap-2 font-semibold text-slate-900 group-hover:text-blue-700"><x-icon name="chart"/> {{ $report['title'] }}</div>
                <p class="mt-1 text-sm text-slate-500">{{ $report['description'] }}</p>
            </a>
        @endforeach
    </div>
@endsection
