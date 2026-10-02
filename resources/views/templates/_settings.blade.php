{{-- Template settings fields shared by create/edit. --}}
@if ($kind === 'id_card')
    <x-form.input name="name" label="Template name" :value="$template->name" required class="sm:col-span-2"/>
    <x-form.select name="type" label="Card type" :options="\App\Models\IdCardTemplate::TYPES" :value="$template->type ?? 'STUDENT_ID'" required/>
    <x-form.select name="dpi" label="Print resolution (DPI)" :options="[150 => '150', 200 => '200', 300 => '300 (recommended)', 600 => '600']" :value="$template->dpi ?? setting('default_id_dpi')" required/>
    <div class="sm:col-span-2">
        <span class="form-label">Card size</span>
        <div class="mb-2 flex flex-wrap gap-1">
            <button type="button" class="btn btn-secondary btn-sm" data-size="85.6x53.98">CR80 landscape</button>
            <button type="button" class="btn btn-secondary btn-sm" data-size="53.98x85.6">CR80 portrait</button>
            <button type="button" class="btn btn-secondary btn-sm" data-size="{{ setting('default_id_width_mm') }}x{{ setting('default_id_height_mm') }}">System default</button>
        </div>
        <div class="grid grid-cols-2 gap-3">
            <x-form.input name="width_mm" type="number" step="0.01" label="Width (mm)" :value="$template->width_mm ?? setting('default_id_width_mm')" required/>
            <x-form.input name="height_mm" type="number" step="0.01" label="Height (mm)" :value="$template->height_mm ?? setting('default_id_height_mm')" required/>
        </div>
        <p class="form-hint">CR80 (85.6 × 53.98 mm) is the standard PVC card size supported by card printers.</p>
    </div>
    <label class="flex items-center gap-2 text-sm sm:col-span-2">
        <input type="hidden" name="has_back" value="0">
        <input type="checkbox" name="has_back" value="1" class="form-check" @checked(old('has_back', $template->exists ? $template->has_back : true))> Two-sided card (front and back)
    </label>
@else
    <x-form.input name="name" label="Template name" :value="$template->name" required class="sm:col-span-2"/>
    <x-form.select name="paper_size" label="Paper size" :options="['A4' => 'A4', 'A5' => 'A5', 'A3' => 'A3', 'LETTER' => 'Letter', 'CUSTOM' => 'Custom size']" :value="$template->paper_size ?? setting('default_certificate_paper')" required/>
    <x-form.select name="orientation" label="Orientation" :options="['landscape' => 'Landscape', 'portrait' => 'Portrait']" :value="$template->orientation ?? setting('default_certificate_orientation')" required/>
    <x-form.input name="width_mm" type="number" step="0.1" label="Custom width (mm)" :value="$template->paper_size === 'CUSTOM' ? $template->width_mm : null" hint="Only for custom paper size."/>
    <x-form.input name="height_mm" type="number" step="0.1" label="Custom height (mm)" :value="$template->paper_size === 'CUSTOM' ? $template->height_mm : null"/>
    <x-form.input name="default_title" label="Default certificate title" :value="$template->default_title" class="sm:col-span-2" hint="Pre-filled when generating, e.g. Certificate of Completion."/>
    <x-form.textarea name="default_body" label="Default description / body text" :value="$template->default_body" class="sm:col-span-2" rows="3"/>
@endif

@push('scripts')
<script>
    document.querySelectorAll('[data-size]').forEach((btn) => btn.addEventListener('click', () => {
        const [w, h] = btn.dataset.size.split('x');
        document.getElementById('f_width_mm').value = w;
        document.getElementById('f_height_mm').value = h;
    }));
</script>
@endpush
