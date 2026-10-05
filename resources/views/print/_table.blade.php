<table class="table">
    <thead>
    <tr><th>Job</th>@if (tenant()->isAllSchools())<th>School</th>@endif<th>Type</th><th>Template</th><th class="text-right">Qty</th><th>Progress</th><th>Status</th><th>By</th><th>Created</th><th></th></tr>
    </thead>
    <tbody>
    @forelse ($jobs as $job)
        <tr>
            <td><a href="{{ route('print.show', $job) }}" class="font-medium whitespace-nowrap text-blue-700 hover:underline">{{ $job->job_number }}</a></td>
            @if (tenant()->isAllSchools())<td>{{ $job->school->school_code }}</td>@endif
            <td>{{ $job->typeLabel() }}{{ $job->option('reprint') ? ' (reprint)' : '' }}</td>
            <td class="max-w-52 truncate">{{ $job->template_name }}</td>
            <td class="text-right tabular-nums">{{ $job->total_items }}</td>
            <td class="w-32">
                <div class="h-1.5 rounded bg-slate-100"><div class="h-1.5 rounded {{ $job->failed_items ? 'bg-amber-500' : 'bg-emerald-500' }}" style="width: {{ $job->progressPercent() }}%"></div></div>
                <div class="mt-0.5 text-[11px] text-slate-500 tabular-nums">{{ $job->completed_items }} done{{ $job->failed_items ? ', '.$job->failed_items.' failed' : '' }}</div>
            </td>
            <td><x-status :value="$job->status"/></td>
            <td class="whitespace-nowrap">{{ $job->user?->name ?? '—' }}</td>
            <td class="whitespace-nowrap text-slate-500" title="{{ $job->created_at }}">{{ $job->created_at->diffForHumans() }}</td>
            <td class="text-right whitespace-nowrap">
                @if ($job->completed_items > 0)
                    @can('print', $job)
                        <a href="{{ route('print.browser', $job) }}" target="_blank" class="btn btn-secondary btn-sm"><x-icon name="printer"/> Print</a>
                        <form method="POST" action="{{ route('print.direct', $job) }}" class="inline" data-confirm="Send this job directly to {{ setting('direct_print_printer', 'EPSON L8050 Series') }}?">
                            @csrf
                            <button class="btn btn-secondary btn-sm"><x-icon name="printer"/> Direct</button>
                        </form>
                        @if ($job->file_path)<a href="{{ route('print.pdf', $job) }}" class="btn btn-secondary btn-sm"><x-icon name="download"/> PDF</a>@endif
                    @endcan
                @endif
            </td>
        </tr>
    @empty
        <tr><td colspan="10"><x-empty icon="printer" :title="$emptyTitle">{{ $emptyText }}</x-empty></td></tr>
    @endforelse
    </tbody>
</table>
