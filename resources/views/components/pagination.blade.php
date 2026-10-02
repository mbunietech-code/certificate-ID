@if ($paginator->hasPages())
    <nav class="flex flex-wrap items-center justify-between gap-3 text-sm" aria-label="Pagination">
        <p class="text-slate-500">
            Showing <span class="font-medium text-slate-700">{{ $paginator->firstItem() }}</span>–<span class="font-medium text-slate-700">{{ $paginator->lastItem() }}</span>
            of <span class="font-medium text-slate-700">{{ number_format($paginator->total()) }}</span>
        </p>
        <div class="flex flex-wrap items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-50">‹ Prev</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary btn-sm" rel="prev">‹ Prev</a>
            @endif
            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="px-1 text-slate-400">…</span>
                @endif
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="btn btn-primary btn-sm" aria-current="page">{{ $page }}</span>
                        @else
                            <a href="{{ $url }}" class="btn btn-secondary btn-sm">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary btn-sm" rel="next">Next ›</a>
            @else
                <span class="btn btn-secondary btn-sm opacity-50">Next ›</span>
            @endif
        </div>
    </nav>
@endif
