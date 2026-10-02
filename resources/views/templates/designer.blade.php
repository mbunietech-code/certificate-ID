@extends('layouts.app')
@section('title', 'Design · '.$template->name)
@push('head')
    @vite('resources/js/designer.js')
    <style>
        #dz-stage { position: relative; display: flex; align-items: flex-start; justify-content: center; padding: 24px; min-height: 60vh;
            background: repeating-conic-gradient(#e2e8f0 0% 25%, #f1f5f9 0% 50%) 50% / 20px 20px; border-radius: 8px; overflow: auto; touch-action: none; }
        #dz-card { position: relative; flex-shrink: 0; overflow: hidden; box-shadow: 0 6px 24px rgba(15, 23, 42, .25); user-select: none; }
        #dz-card.dz-grid::after { content: ''; position: absolute; inset: 0; pointer-events: none; z-index: 5000;
            background-image: linear-gradient(to right, rgba(37, 99, 235, .08) 1px, transparent 1px), linear-gradient(to bottom, rgba(37, 99, 235, .08) 1px, transparent 1px);
            background-size: var(--grid) var(--grid); }
        .dz-el { position: absolute; cursor: move; transform-origin: 50% 50%; }
        .dz-el.dz-locked { cursor: default; }
        .dz-el:hover { outline: 1px dashed rgba(37, 99, 235, .6); }
        .dz-muted { color: #94a3b8; font-style: italic; }
        .dz-placeholder { width: 100%; height: 100%; display: grid; place-items: center; border: 1px dashed #94a3b8; color: #64748b;
            font: 10px/1.1 system-ui, sans-serif; text-align: center; background: rgba(241, 245, 249, .7); }
        .dz-silhouette { position: relative; width: 100%; height: 100%; background: #d5e8ea; }
        .dz-silhouette span:first-child { position: absolute; left: 29%; top: 14%; width: 42%; aspect-ratio: 1; border-radius: 50%; background: #9bbfc5; }
        .dz-silhouette span:last-child { position: absolute; left: 13%; top: 66%; width: 74%; height: 60%; border-radius: 50%; background: #9bbfc5; }
        .dz-sel { position: absolute; outline: 2px solid #2563eb; pointer-events: none; transform-origin: 50% 50%; }
        .dz-h { position: absolute; width: 10px; height: 10px; background: #fff; border: 2px solid #2563eb; border-radius: 2px; pointer-events: auto; }
        .dz-h-nw { left: -6px; top: -6px; cursor: nwse-resize; } .dz-h-n { left: calc(50% - 5px); top: -6px; cursor: ns-resize; }
        .dz-h-ne { right: -6px; top: -6px; cursor: nesw-resize; } .dz-h-e { right: -6px; top: calc(50% - 5px); cursor: ew-resize; }
        .dz-h-se { right: -6px; bottom: -6px; cursor: nwse-resize; } .dz-h-s { left: calc(50% - 5px); bottom: -6px; cursor: ns-resize; }
        .dz-h-sw { left: -6px; bottom: -6px; cursor: nesw-resize; } .dz-h-w { left: -6px; top: calc(50% - 5px); cursor: ew-resize; }
    </style>
@endpush

@section('content')
    <script type="application/json" id="designer-config">@json($config)</script>

    <div class="mb-3 flex flex-wrap items-center gap-2">
        <a href="{{ route($routePrefix.'.index') }}" class="btn btn-ghost btn-sm">← Templates</a>
        <div class="min-w-0">
            <h1 class="truncate text-base font-semibold text-slate-900">{{ $template->name }}</h1>
            <p class="text-xs text-slate-500">{{ $config['width'] }} × {{ $config['height'] }} mm @if ($template->isGlobal())· <span class="text-violet-700">Global template</span>@endif</p>
        </div>
        @if (count($config['sides']) > 1)
            <div class="ml-2 flex gap-1">
                <button type="button" class="btn btn-sm btn-primary" data-side="front">Front</button>
                <button type="button" class="btn btn-sm btn-secondary" data-side="back">Back</button>
            </div>
        @endif
        <div class="ml-auto flex flex-wrap items-center gap-1">
            <span id="dz-status" class="mr-2 text-xs text-slate-500"></span>
            <button type="button" class="btn btn-secondary btn-sm" data-action="undo" title="Undo (Ctrl+Z)">↶</button>
            <button type="button" class="btn btn-secondary btn-sm" data-action="redo" title="Redo (Ctrl+Y)">↷</button>
            <button type="button" class="btn btn-primary btn-sm" data-action="toggle-snap" title="Snap to 0.5 mm grid (hold Alt to move freely)">Grid</button>
            <button type="button" class="btn btn-secondary btn-sm" data-action="zoom-out">−</button>
            <span id="dz-zoom-label" class="w-12 text-center text-xs tabular-nums text-slate-600"></span>
            <button type="button" class="btn btn-secondary btn-sm" data-action="zoom-in">+</button>
            <button type="button" class="btn btn-secondary btn-sm" data-action="zoom-fit">Fit</button>
            <button type="button" class="btn btn-secondary btn-sm" data-action="preview"><x-icon name="eye"/> Preview</button>
            <button type="button" class="btn btn-success btn-sm" data-action="save"><x-icon name="check"/> Save</button>
        </div>
    </div>

    <div class="grid gap-3 lg:grid-cols-[220px_minmax(0,1fr)_290px]">
        {{-- Left: add elements + layers --}}
        <aside class="space-y-3">
            <div class="card p-3">
                <h3 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Add</h3>
                <div class="grid grid-cols-2 gap-1">
                    <button type="button" class="btn btn-secondary btn-sm" data-add="text">Text</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-add="qr">QR code</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-add="barcode">Barcode</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-add="rect">Rectangle</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-add="rect" data-extra='{"ellipse":true,"w":15,"h":15}'>Ellipse</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-add="line">Line</button>
                </div>
                <label class="mt-2 block">
                    <span class="sr-only">Add data field</span>
                    <select id="dz-add-field" class="form-input form-input-sm">
                        <option value="">+ Data field…</option>
                        @foreach ($config['placeholders'] as $group => $items)
                            <optgroup label="{{ $group }}">
                                @foreach ($items as $key => $label)<option value="{{ '{'.$key.'}' }}">{{ $label }}</option>@endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
                <label class="mt-1 block">
                    <span class="sr-only">Add image</span>
                    <select id="dz-add-image" class="form-input form-input-sm">
                        <option value="">+ Image…</option>
                        @foreach ($config['imageSources'] as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
            <div class="card p-2">
                <h3 class="mb-1 px-1 text-xs font-semibold tracking-wide text-slate-500 uppercase">Layers</h3>
                <div id="dz-layers" class="max-h-[50vh] space-y-0.5 overflow-y-auto"></div>
            </div>
        </aside>

        {{-- Canvas --}}
        <div id="dz-stage"></div>

        {{-- Properties --}}
        <aside class="card self-start p-3">
            <div id="dz-props"></div>
        </aside>
    </div>
    <p class="mt-2 text-xs text-slate-500">
        Preview uses {{ $config['sample']['full_name'] ?? 'sample data' }} and the school's real logo, colors and signature.
        Keyboard: arrows nudge · Shift+arrows 5 mm · Del delete · Ctrl+D duplicate · Ctrl+Z undo · Ctrl+S save.
    </p>
@endsection
