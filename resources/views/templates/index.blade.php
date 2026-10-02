@extends('layouts.app')
@php $isCard = $kind === 'id_card'; @endphp
@section('title', $isCard ? 'ID card templates' : 'Certificate templates')
@section('header')
    <h1 class="page-title">{{ $isCard ? 'ID card templates' : 'Certificate templates' }}</h1>
    <p class="page-subtitle">Global templates are shared with every school and follow each school's logo, colors and signature.</p>
@endsection
@section('actions')
    @can('templates.manage')<a href="{{ route($routePrefix.'.create') }}" class="btn btn-primary"><x-icon name="plus"/> New template</a>@endcan
@endsection

@section('content')
    @if ($isCard)
        <div class="mb-3 flex gap-1">
            @foreach (['' => 'All', 'STUDENT_ID' => 'Student IDs', 'STAFF_ID' => 'Staff IDs'] as $type => $label)
                <a href="{{ route($routePrefix.'.index', array_filter(['type' => $type])) }}" class="btn btn-sm {{ request('type', '') === $type ? 'btn-primary' : 'btn-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
    @endif

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        @forelse ($templates as $template)
            @php
                $ratio = $template->width_mm / max(1, $template->height_mm);
                $canEdit = auth()->user()->can('update', $template);
            @endphp
            <div class="card flex flex-col">
                <div class="flex h-36 items-center justify-center rounded-t-lg bg-slate-100 p-3">
                    <div class="relative flex items-center justify-center rounded border border-slate-300 bg-white text-center text-[10px] text-slate-500 shadow-sm"
                         style="aspect-ratio: {{ $ratio }}; {{ $ratio >= 1 ? 'width: 70%' : 'height: 100%' }}">
                        <span>{{ rtrim(rtrim(number_format($template->width_mm, 2), '0'), '.') }} × {{ rtrim(rtrim(number_format($template->height_mm, 2), '0'), '.') }} mm</span>
                        <span class="absolute top-0 left-0 h-1.5 w-full rounded-t" style="background: {{ $template->school?->primary_color ?? '#1e3a8a' }}"></span>
                    </div>
                </div>
                <div class="flex flex-1 flex-col gap-2 p-3">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="truncate font-medium text-slate-900">{{ $template->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ $isCard ? $template->typeLabel().' · '.($template->has_back ? 'Front & back' : 'Front only') : $template->sizeLabel() }}
                            </div>
                        </div>
                        <x-status :value="$template->status"/>
                    </div>
                    <div class="flex flex-wrap gap-1 text-xs">
                        @if ($template->isGlobal())
                            <span class="badge badge-violet">Global</span>
                        @else
                            <span class="badge badge-slate">{{ $template->school?->school_code }}</span>
                        @endif
                        @if ($template->is_default)<span class="badge badge-blue">Default</span>@endif
                    </div>
                    <div class="mt-auto flex flex-wrap gap-1 pt-1">
                        @if ($canEdit)
                            <a href="{{ route($routePrefix.'.design', $template) }}" class="btn btn-primary btn-sm"><x-icon name="edit"/> Design</a>
                            <a href="{{ route($routePrefix.'.edit', $template) }}" class="btn btn-secondary btn-sm"><x-icon name="cog"/> Settings</a>
                        @endif
                        <a href="{{ route($routePrefix.'.preview', $template) }}" target="_blank" class="btn btn-secondary btn-sm"><x-icon name="eye"/> Preview</a>
                        @can('duplicate', $template)
                            <form method="POST" action="{{ route($routePrefix.'.duplicate', $template) }}">@csrf
                                <button class="btn btn-secondary btn-sm" title="{{ $template->isGlobal() && ! auth()->user()->isSuperAdmin() ? 'Copy into your school to customise it' : 'Duplicate' }}"><x-icon name="copy"/> {{ $template->isGlobal() && ! $canEdit ? 'Copy to my school' : 'Duplicate' }}</button>
                            </form>
                        @endcan
                    </div>
                </div>
            </div>
        @empty
            <div class="card sm:col-span-2 xl:col-span-3 2xl:col-span-4"><x-empty icon="template" title="No templates yet">Create a template from one of the ready-made designs.</x-empty></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $templates->links() }}</div>
@endsection
