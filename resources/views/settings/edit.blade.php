@extends('layouts.app')
@section('title', 'System settings')
@section('header')<h1 class="page-title">System settings</h1><p class="page-subtitle">Defaults for every school. Schools override numbering and branding in their own profile.</p>@endsection

@section('content')
    <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data" class="grid gap-4 xl:grid-cols-2">
        @csrf @method('PUT')
        <div class="card">
            <div class="card-header"><h2 class="card-title">General</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-2">
                <x-form.input name="system_name" label="System name" :value="$settings['system_name']" required class="sm:col-span-2"/>
                <x-form.input name="organization_name" label="Organization name" :value="$settings['organization_name']" class="sm:col-span-2"/>
                <x-form.image name="system_logo" label="System logo" :path="$settings['system_logo']" remove-name="remove_logo" class="sm:col-span-2"/>
                <x-form.select name="date_format" label="Date format" :options="collect(['d/m/Y', 'd-m-Y', 'Y-m-d', 'm/d/Y', 'd M Y', 'j F Y'])->mapWithKeys(fn ($f) => [$f => now()->format($f).'  ('.$f.')'])->all()" :value="$settings['date_format']" required/>
                <x-form.input name="verification_base_url" type="url" label="QR verification base URL" :value="$settings['verification_base_url']" placeholder="{{ config('app.url') }}"
                    hint="Public address encoded in QR codes, e.g. https://ids.example.com. Leave blank to use APP_URL."/>
                <x-form.input name="queue_threshold" type="number" label="Run jobs in background above (items)" :value="$settings['queue_threshold']" required
                    hint="Smaller jobs are processed right after the request; larger ones need the queue worker."/>
            </div>
        </div>

        <div class="space-y-4">
            <div class="card">
                <div class="card-header"><h2 class="card-title">ID cards</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-3">
                    <x-form.input name="default_id_width_mm" type="number" step="0.01" label="Default width (mm)" :value="$settings['default_id_width_mm']" required/>
                    <x-form.input name="default_id_height_mm" type="number" step="0.01" label="Default height (mm)" :value="$settings['default_id_height_mm']" required/>
                    <x-form.select name="default_id_dpi" label="Default DPI" :options="['150' => '150', '200' => '200', '300' => '300', '600' => '600']" :value="$settings['default_id_dpi']" required/>
                    <x-form.select name="default_paper_size" label="Sheet paper" :options="['A4' => 'A4', 'LETTER' => 'Letter']" :value="$settings['default_paper_size']" required/>
                    <x-form.input name="id_card_validity_months" type="number" label="Validity (months)" :value="$settings['id_card_validity_months']" required hint="Used when no academic year end date is set."/>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Certificates</h2></div>
                <div class="card-body grid gap-4 sm:grid-cols-2">
                    <x-form.select name="default_certificate_paper" label="Default paper" :options="['A4' => 'A4', 'A5' => 'A5', 'A3' => 'A3', 'LETTER' => 'Letter']" :value="$settings['default_certificate_paper']" required/>
                    <x-form.select name="default_certificate_orientation" label="Default orientation" :options="['landscape' => 'Landscape', 'portrait' => 'Portrait']" :value="$settings['default_certificate_orientation']" required/>
                </div>
            </div>
            <div class="card">
                <div class="card-header"><h2 class="card-title">Default numbering formats (new schools)</h2></div>
                <div class="card-body grid gap-4">
                    <x-form.input name="default_student_id_format" label="Student IDs" :value="$settings['default_student_id_format']" required/>
                    <x-form.input name="default_staff_id_format" label="Staff IDs" :value="$settings['default_staff_id_format']" required/>
                    <x-form.input name="default_certificate_number_format" label="Certificates" :value="$settings['default_certificate_number_format']" required
                        hint="Tokens: {CODE} {YEAR} {YY} {MM} {SEQ:n}."/>
                </div>
            </div>
        </div>
        <div class="flex justify-end xl:col-span-2"><button class="btn btn-primary">Save settings</button></div>
    </form>
@endsection
