@props(['name', 'label', 'path' => null, 'removeName' => null, 'removeValue' => null, 'hint' => 'JPG, PNG or WEBP, max 4 MB.', 'round' => false])
<div {{ $attributes->only('class') }}>
    <label class="form-label" for="f_{{ $name }}">{{ $label }}</label>
    <div class="flex items-center gap-3">
        <div class="grid size-16 shrink-0 place-items-center overflow-hidden border border-slate-200 bg-slate-50 {{ $round ? 'rounded-full' : 'rounded' }}">
            @if ($path)
                <img src="{{ Storage::disk('public')->url($path) }}" alt="" class="size-full {{ $round ? 'object-cover' : 'object-contain' }}">
            @else
                <x-icon name="upload" class="size-5 text-slate-300"/>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <input type="file" id="f_{{ $name }}" name="{{ $name }}" accept="image/jpeg,image/png,image/webp"
                class="block w-full text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-medium">
            @if ($path && $removeName)
                <label class="mt-1 inline-flex items-center gap-1.5 text-xs text-slate-600">
                    <input type="checkbox" class="form-check" name="{{ $removeName }}" value="{{ $removeValue ?? 1 }}"> Remove
                </label>
            @endif
            <p class="form-hint">{{ $hint }}</p>
            @error($name)<p class="form-error">{{ $message }}</p>@enderror
        </div>
    </div>
</div>
