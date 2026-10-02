@extends('layouts.app')
@section('title', 'Print history')
@section('header')
    <h1 class="page-title">Print history</h1>
    <p class="page-subtitle">{{ number_format($jobs->total()) }} print job(s)</p>
@endsection
@section('actions')
    @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
        <a href="{{ route('print.history.export', request()->query() + ['format' => $format]) }}" class="btn btn-secondary btn-sm"><x-icon name="download"/> {{ $label }}</a>
    @endforeach
@endsection

@section('content')
    <div class="card">
        <form method="GET" class="card-header">
            <div class="flex flex-wrap items-end gap-2">
                <div><label class="form-label">Job no.</label><input name="job_number" value="{{ $filters['job_number'] ?? '' }}" class="form-input w-40"></div>
                @if ($schools->isNotEmpty() && tenant()->isAllSchools())
                    <div><label class="form-label">School</label>
                        <select name="school_id" class="form-input w-48"><option value="">All</option>@foreach ($schools as $s)<option value="{{ $s->id }}" @selected(($filters['school_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                @endif
                <div><label class="form-label">User</label>
                    <select name="user_id" class="form-input w-40"><option value="">All</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div><label class="form-label">Type</label>
                    <select name="type" class="form-input w-32"><option value="">All</option>@foreach (\App\Models\PrintJob::TYPES as $k => $v)<option value="{{ $k }}" @selected(($filters['type'] ?? '') === $k)>{{ $v }}</option>@endforeach</select></div>
                <div><label class="form-label">Status</label>
                    <select name="status" class="form-input w-32"><option value="">All</option>@foreach (\App\Models\PrintJob::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                <div><label class="form-label">From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input"></div>
                <div><label class="form-label">To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-input"></div>
                <button class="btn btn-secondary"><x-icon name="search"/> Filter</button>
                @if (array_filter($filters))<a href="{{ route('print.history') }}" class="btn btn-ghost">Clear</a>@endif
            </div>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Job no.</th><th>School</th><th>User</th><th>Document type</th><th>Template</th><th class="text-right">Quantity</th><th>Status</th><th class="text-right">Printed</th><th>IP address</th><th>Date</th></tr></thead>
                <tbody>
                @forelse ($jobs as $job)
                    <tr>
                        <td><a href="{{ route('print.show', $job) }}" class="font-medium whitespace-nowrap text-blue-700 hover:underline">{{ $job->job_number }}</a></td>
                        <td>{{ $job->school->school_code }}</td>
                        <td class="whitespace-nowrap">{{ $job->user?->name ?? '—' }}</td>
                        <td>{{ $job->typeLabel() }}{{ $job->option('reprint') ? ' (reprint)' : '' }}</td>
                        <td class="max-w-52 truncate">{{ $job->template_name }}</td>
                        <td class="text-right tabular-nums">{{ $job->completed_items }}/{{ $job->total_items }}</td>
                        <td><x-status :value="$job->status"/></td>
                        <td class="text-right tabular-nums">{{ $job->print_count }}×</td>
                        <td class="font-mono text-xs">{{ $job->ip_address }}</td>
                        <td class="whitespace-nowrap">{{ $job->created_at->format('d/m/Y H:i') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10"><x-empty icon="history" title="No print jobs match the filters"/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $jobs->links() }}</div>
    </div>
@endsection
