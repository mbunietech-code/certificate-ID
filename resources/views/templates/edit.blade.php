@extends('layouts.app')
@section('title', 'Template settings')
@section('header')
    <h1 class="page-title">Template settings</h1>
    <p class="page-subtitle">{{ $template->name }} @if ($template->isGlobal())<span class="badge badge-violet ml-1">Global</span>@endif</p>
@endsection
@section('actions')
    <a href="{{ route($routePrefix.'.design', $template) }}" class="btn btn-secondary"><x-icon name="edit"/> Open designer</a>
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-3">
        <form method="POST" action="{{ route($routePrefix.'.update', $template) }}" class="card xl:col-span-2">
            @csrf @method('PUT')
            <div class="card-body grid gap-4 sm:grid-cols-2">
                @include('templates._settings')
                <x-form.select name="status" label="Status" :options="['active' => 'Active (available for generation)', 'inactive' => 'Inactive']" :value="$template->status" required/>
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                <a href="{{ route($routePrefix.'.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary">Save settings</button>
            </div>
        </form>
        <div class="card self-start">
            <div class="card-header"><h2 class="card-title text-red-700">Delete template</h2></div>
            <div class="card-body text-sm text-slate-600">
                <p class="mb-3">Documents already issued with this template stay valid and can still be verified.</p>
                <form method="POST" action="{{ route($routePrefix.'.destroy', $template) }}" data-confirm="Delete template {{ $template->name }}?">
                    @csrf @method('DELETE') <button class="btn btn-danger"><x-icon name="trash"/> Delete template</button>
                </form>
            </div>
        </div>
    </div>
@endsection
