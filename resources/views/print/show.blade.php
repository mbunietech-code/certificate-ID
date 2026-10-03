@extends('layouts.app')
@section('title', 'Print job '.$job->job_number)
@section('header')
    <h1 class="page-title">Print job {{ $job->job_number }}</h1>
    <p class="page-subtitle">{{ $job->total_items }} × {{ $job->typeLabel() }}{{ $job->option('reprint') ? ' (reprint)' : '' }} · {{ $job->template_name }} · {{ $job->school->name }}</p>
@endsection
@section('actions')
    @can('print', $job)
        @if (in_array($job->status, ['pending', 'processing']))
            <form method="POST" action="{{ route('print.cancel', $job) }}" data-confirm="Cancel this print job?">@csrf <button class="btn btn-secondary">Cancel job</button></form>
        @endif
        @if (in_array($job->status, ['failed', 'cancelled']) || $job->failed_items > 0 || ($job->status === 'pending' && $job->created_at->lt(now()->subMinutes(2))))
            <form method="POST" action="{{ route('print.retry', $job) }}">@csrf <button class="btn btn-secondary"><x-icon name="refresh"/> Retry</button></form>
        @endif
    @endcan
@endsection

@section('content')
    @if (! $job->isFinished())
        <div class="card mb-4" data-poll-url="{{ route('print.status', $job) }}">
            <div class="card-body">
                <div class="mb-2 flex items-center justify-between text-sm">
                    <span class="font-medium text-slate-800">{{ $job->status === 'pending' ? 'Waiting to start…' : 'Generating documents…' }}</span>
                    <span class="tabular-nums text-slate-500" data-progress-label>{{ $job->completed_items + $job->failed_items }} / {{ $job->total_items }}</span>
                </div>
                <div class="h-2.5 rounded bg-slate-100"><div class="h-2.5 rounded bg-blue-600 transition-all" style="width: {{ $job->progressPercent() }}%" data-progress-bar></div></div>
                <p class="mt-2 text-xs text-slate-500">This page refreshes automatically when the job is finished.</p>
                <div class="alert alert-warning mt-3" data-waiting hidden>
                    This job is waiting for the background worker. Make sure <code>php artisan queue:work</code> is running on the server (see installation notes).
                </div>
            </div>
        </div>
    @elseif ($job->status === 'completed')
        <div class="card mb-4 border-emerald-200">
            <div class="card-body flex flex-wrap items-center gap-4">
                <span class="grid size-12 place-items-center rounded-full bg-emerald-100 text-emerald-700"><x-icon name="check" class="size-6"/></span>
                <div class="mr-auto">
                    <div class="font-semibold text-slate-900">Ready to print – {{ $job->completed_items }} document(s)</div>
                    <div class="text-sm text-slate-500">
                        Layout: {{ $job->isIdCardJob() ? ($job->option('layout') === 'sheet' ? 'cards on '.$job->option('paper', 'A4').' sheets' : 'one card per page (PVC printer)') : 'one certificate per page' }}.
                        @if ($job->isIdCardJob())
                            Side: {{ $job->option('include_back') ? 'front + back' : 'front only' }}.
                        @endif
                        Print at 100% / “Actual size” with no margins. Printed {{ $job->print_count }} time(s).
                    </div>
                </div>
                @can('print', $job)
                    <a href="{{ route('print.browser', $job) }}" target="_blank" class="btn btn-primary btn-lg"><x-icon name="printer"/> Print</a>
                    @if ($job->file_path)<a href="{{ route('print.pdf', $job) }}" class="btn btn-secondary btn-lg"><x-icon name="download"/> Download PDF</a>@endif
                @endcan
            </div>
        </div>
    @else
        <div class="alert alert-error mb-4">This job {{ $job->status }}. {{ $job->error_message }}</div>
    @endif

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Job details</h2></div>
            <dl class="card-body">
                @foreach ([
                    'Status' => ucfirst($job->status), 'Created by' => $job->user?->name, 'Created' => $job->created_at->format('d/m/Y H:i'),
                    'Started' => $job->started_at?->format('d/m/Y H:i:s'), 'Finished' => $job->completed_at?->format('d/m/Y H:i:s'),
                    'Generated' => $job->completed_items.' of '.$job->total_items, 'Failed' => (string) $job->failed_items,
                    'Template' => $template?->name ?? $job->template_name, 'IP address' => $job->ip_address,
                    'Side' => $job->isIdCardJob() ? ($job->option('include_back') ? 'Front + back' : 'Front only') : null,
                    'Last printed' => $job->last_printed_at?->diffForHumans(),
                ] as $label => $value)
                    @if ($value !== null)<div class="dl-row"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                @endforeach
                @if ($job->type === 'certificate' && ! $job->option('reprint'))
                    <div class="dl-row"><dt>Title</dt><dd>{{ $job->option('title') }}</dd></div>
                    <div class="dl-row"><dt>Programme</dt><dd>{{ $job->option('program') ?: '—' }}</dd></div>
                    <div class="dl-row"><dt>Date of issue</dt><dd>{{ format_date($job->option('issued_on')) }}</dd></div>
                @endif
            </dl>
        </div>
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Failed items</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>#</th><th>Person</th><th>Error</th></tr></thead>
                    <tbody>
                    @forelse ($failed as $item)
                        <tr><td>{{ $item->position + 1 }}</td><td>{{ $item->subject?->full_name ?? 'Record removed' }}</td><td class="text-red-700">{{ $item->error }}</td></tr>
                    @empty
                        <tr><td colspan="3"><x-empty icon="check" title="No failed items"/></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
