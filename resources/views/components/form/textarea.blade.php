@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'hint' => null, 'required' => false])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="text-red-600">*</span>@endif</label>@endif
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}" {{ $attributes->except(['class', 'id'])->merge(['class' => 'form-input'.($errors->has($key) ? ' border-red-500' : '')]) }} @required($required)>{{ old($key, $value) }}</textarea>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    @error($key)<p class="form-error">{{ $message }}</p>@enderror
</div>
