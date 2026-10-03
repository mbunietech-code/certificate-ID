@props(['name', 'label', 'path' => null, 'removeName' => null, 'removeValue' => null, 'hint' => 'JPG, PNG or WEBP, max 4 MB.', 'round' => false, 'camera' => false])
<div {{ $attributes->only('class') }}>
    <label class="form-label" for="f_{{ $name }}">{{ $label }}</label>
    <div class="flex items-start gap-3" @if ($camera) data-camera-field @endif>
        <div class="grid size-16 shrink-0 place-items-center overflow-hidden border border-slate-200 bg-slate-50 {{ $round ? 'rounded-full' : 'rounded' }}">
            @if ($path)
                <img src="{{ Storage::disk('public')->url($path) }}" alt="" class="size-full {{ $round ? 'object-cover' : 'object-contain' }}" data-camera-preview>
            @else
                <x-icon name="upload" class="size-5 text-slate-300" data-camera-placeholder/>
                <img src="" alt="" class="hidden size-full {{ $round ? 'object-cover' : 'object-contain' }}" data-camera-preview>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <input type="file" id="f_{{ $name }}" name="{{ $name }}" accept="image/jpeg,image/png,image/webp"
                class="block w-full text-xs text-slate-600 file:mr-2 file:rounded file:border-0 file:bg-slate-100 file:px-2 file:py-1 file:text-xs file:font-medium" @if ($camera) data-camera-input @endif>
            @if ($camera)
                <div class="mt-2 space-y-2 rounded-md border border-slate-200 bg-slate-50 p-2" data-camera-panel hidden>
                    <div class="relative overflow-hidden rounded bg-slate-900">
                        <video class="aspect-[4/3] w-full object-cover" playsinline autoplay muted data-camera-video></video>
                        <div class="pointer-events-none absolute inset-0 grid place-items-center">
                            <div class="h-[72%] aspect-square rounded-full border-2 border-white shadow-[0_0_0_999px_rgba(15,23,42,.42)]"></div>
                        </div>
                    </div>
                    <canvas hidden data-camera-canvas></canvas>
                </div>
                <div class="mt-2 flex flex-wrap gap-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-camera-start><x-icon name="camera"/> Use camera</button>
                    <button type="button" class="btn btn-primary btn-sm" data-camera-capture hidden><x-icon name="check"/> Capture</button>
                    <button type="button" class="btn btn-secondary btn-sm" data-camera-stop hidden><x-icon name="x"/> Close camera</button>
                </div>
                <p class="form-error" data-camera-error hidden></p>
            @endif
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
