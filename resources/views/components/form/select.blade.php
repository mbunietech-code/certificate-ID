@props(['name', 'label' => null, 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false])
@php
    $id = $attributes->get('id', 'f_'.str_replace(['[', ']', '.'], '_', $name));
    $key = str_replace(['[', ']'], ['.', ''], $name);
    $selected = (string) old($key, $value);
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)<label for="{{ $id }}" class="form-label">{{ $label }}@if ($required) <span class="text-red-600">*</span>@endif</label>@endif
    <select id="{{ $id }}" name="{{ $name }}" {{ $attributes->except(['class', 'id'])->merge(['class' => 'form-input'.($errors->has($key) ? ' border-red-500' : '')]) }} @required($required)>
        @if ($placeholder !== null)<option value="">{{ $placeholder }}</option>@endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected($selected === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    @error($key)<p class="form-error">{{ $message }}</p>@enderror
</div>
