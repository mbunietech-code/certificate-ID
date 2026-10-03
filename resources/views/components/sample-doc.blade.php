@props(['html', 'w', 'h', 'scale' => 1])
{{-- A rendered template side (millimetre markup from TemplateRenderer), scaled for display. --}}
@php
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
@endphp
<div {{ $attributes->merge(['class' => 'sample-doc']) }} style="width: calc({{ $fmt($w) }}mm * {{ $scale }}); height: calc({{ $fmt($h) }}mm * {{ $scale }});">
    <div class="sample-doc-inner" style="width: {{ $fmt($w) }}mm; height: {{ $fmt($h) }}mm; transform: scale({{ $scale }});">{!! $html !!}</div>
</div>
