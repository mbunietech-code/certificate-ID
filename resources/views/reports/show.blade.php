@extends('layouts.app')
@section('title', $definition['title'])
@section('header')
    <a href="{{ route('reports.index') }}" class="text-xs text-blue-700 hover:underline">← Reports</a>
    <h1 class="page-title">{{ $definition['title'] }}</h1>
    <p class="page-subtitle">{{ $definition['description'] }} · {{ tenant()->school()?->name ?? 'All schools' }}</p>
@endsection
@section('actions')
    @foreach (['csv' => 'CSV', 'xlsx' => 'Excel', 'pdf' => 'PDF'] as $format => $label)
        <a href="{{ route('reports.show', [$key] + request()->query() + ['format' => $format]) }}" class="btn btn-secondary btn-sm"><x-icon name="download"/> {{ $label }}</a>
    @endforeach
@endsection

@section('content')
    <div class="card">
        @if ($definition['dated'])
            <form method="GET" class="card-header">
                <div class="flex flex-wrap items-end gap-2">
                    <div><label class="form-label">From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input"></div>
                    <div><label class="form-label">To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-input"></div>
                    <button class="btn btn-secondary">Apply</button>
                </div>
            </form>
        @endif
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr>@foreach ($headers as $h)<th>{{ $h }}</th>@endforeach</tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    <tr>@foreach ($row as $cell)<td class="{{ is_int($cell) ? 'tabular-nums' : '' }}">{{ is_int($cell) ? number_format($cell) : $cell }}</td>@endforeach</tr>
                @empty
                    <tr><td colspan="{{ count($headers) }}"><x-empty icon="chart" title="No data for this report yet"/></td></tr>
                @endforelse
                </tbody>
                @php $numeric = collect($headers)->keys()->filter(fn ($i) => collect($rows)->every(fn ($r) => is_int($r[$i] ?? null))); @endphp
                @if (count($rows) > 1 && $numeric->isNotEmpty())
                    <tfoot>
                        <tr class="bg-slate-50 font-semibold">
                            @foreach ($headers as $i => $h)
                                <td class="tabular-nums">{{ $numeric->contains($i) ? number_format(collect($rows)->sum(fn ($r) => $r[$i])) : ($i === 0 ? 'Total' : '') }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>
@endsection
