@extends('layouts.app')
@section('title', 'Print center')
@section('header')
    <h1 class="page-title">Print center</h1>
    <p class="page-subtitle">Generate documents, follow the queue and print or download finished jobs.</p>
@endsection
@section('actions')
    @can('id_cards.generate')<a href="{{ route('id-cards.generate') }}" class="btn btn-primary"><x-icon name="id-card"/> Print ID cards</a>@endcan
    @can('certificates.generate')<a href="{{ route('certificates.generate') }}" class="btn btn-secondary"><x-icon name="certificate"/> Print certificates</a>@endcan
    @can('id_cards.view')<a href="{{ route('id-cards.index') }}" class="btn btn-secondary"><x-icon name="refresh"/> Reprint existing</a>@endcan
@endsection

@section('content')
    <div class="grid gap-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Queue <span class="font-normal text-slate-500">({{ $active->count() }} pending / processing)</span></h2></div>
            <div class="overflow-x-auto">
                @include('print._table', ['jobs' => $active, 'emptyTitle' => 'The queue is empty', 'emptyText' => 'New print jobs appear here while documents are generated.'])
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Recently finished</h2>
                <a href="{{ route('print.history') }}" class="text-xs font-medium text-blue-700 hover:underline">Full print history</a>
            </div>
            <div class="overflow-x-auto">
                @include('print._table', ['jobs' => $recent, 'emptyTitle' => 'Nothing printed yet', 'emptyText' => null])
            </div>
        </div>
    </div>
@endsection
