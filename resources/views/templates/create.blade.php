@extends('layouts.app')
@php $isCard = $kind === 'id_card'; @endphp
@section('title', $isCard ? 'New ID card template' : 'New certificate template')
@section('header')
    <h1 class="page-title">{{ $isCard ? 'New ID card template' : 'New certificate template' }}</h1>
    <p class="page-subtitle">
        @if (tenant()->isAllSchools())
            You are in “All Schools” mode: this template will be <strong>global</strong> (available to every school).
        @else
            This template will belong to <strong>{{ tenant()->school()->name }}</strong>.
        @endif
    </p>
@endsection

@section('content')
    <form method="POST" action="{{ route($routePrefix.'.store') }}" class="grid gap-4 xl:grid-cols-3">
        @csrf
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">1. Start from a design</h2></div>
            <div class="card-body grid gap-2 sm:grid-cols-2">
                @foreach ($presets as $key => $label)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 hover:border-blue-400 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                        @php $p = \App\Services\Documents\DesignPresets::get($key); @endphp
                        <input type="radio" name="preset" value="{{ $key }}" class="form-check mt-0.5" @checked(old('preset', $preset) === $key)
                            data-type="{{ $p['type'] ?? '' }}" data-w="{{ $p['width'] }}" data-h="{{ $p['height'] }}" data-orientation="{{ $p['orientation'] }}" data-paper="{{ $p['paper'] ?? '' }}">
                        <span>
                            <span class="block text-sm font-medium text-slate-800">{{ $label }}</span>
                            <span class="block text-xs text-slate-500">{{ $p['width'] }} × {{ $p['height'] }} mm · {{ count($p['design']['front']['elements']) }} elements</span>
                        </span>
                    </label>
                @endforeach
                <p class="form-hint sm:col-span-2">Everything can be changed afterwards in the designer. If you choose a different size, the design is scaled to fit.</p>
            </div>
        </div>
        <div class="card self-start">
            <div class="card-header"><h2 class="card-title">2. Settings</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                @include('templates._settings')
            </div>
            <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
                <a href="{{ route($routePrefix.'.index') }}" class="btn btn-secondary">Cancel</a>
                <button class="btn btn-primary">Create &amp; open designer</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Picking a design also picks its card type / size / orientation (still editable).
    document.querySelectorAll('input[name="preset"]').forEach((radio) => radio.addEventListener('change', () => {
        const set = (id, value) => { const el = document.getElementById(id); if (el && value) el.value = value; };
        set('f_type', radio.dataset.type);
        set('f_orientation', radio.dataset.orientation);
        set('f_paper_size', radio.dataset.paper);
        if (document.getElementById('f_type')) {
            set('f_width_mm', radio.dataset.w);
            set('f_height_mm', radio.dataset.h);
        }
    }));
    document.querySelector('input[name="preset"]:checked')?.dispatchEvent(new Event('change'));
</script>
@endpush
