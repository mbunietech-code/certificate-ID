{{-- Shared by schools.create/edit (super admin) and school-profile.edit (school admin: $profile = true). --}}
@php $profile = $profile ?? false; @endphp
<div class="grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-header"><h2 class="card-title">School details</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <x-form.input name="name" label="School name" :value="$school->name" required class="sm:col-span-2"/>
            @unless ($profile)
                <x-form.input name="school_code" label="School code" :value="$school->school_code" required maxlength="20" hint="Short unique code used in numbers, e.g. BNG."/>
            @endunless
            <x-form.input name="short_name" label="Short name" :value="$school->short_name"/>
            <x-form.input name="registration_number" label="Registration number" :value="$school->registration_number"/>
            <x-form.input name="principal_name" label="Principal / head of school" :value="$school->principal_name"/>
            <x-form.textarea name="address" label="Address" :value="$school->address" rows="2" class="sm:col-span-2"/>
            <x-form.input name="region" label="Region" :value="$school->region"/>
            <x-form.input name="district" label="District" :value="$school->district"/>
            <x-form.input name="ward" label="Ward" :value="$school->ward"/>
            <x-form.input name="phone" label="Phone" :value="$school->phone"/>
            <x-form.input name="email" type="email" label="Email" :value="$school->email"/>
            <x-form.input name="website" label="Website" :value="$school->website"/>
            @if (! $profile && $school->exists)
                <x-form.select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$school->status" required/>
            @endif
        </div>
    </div>

    <div class="space-y-4">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Branding</h2></div>
            <div class="card-body space-y-4">
                <x-form.image name="logo" label="School logo" :path="$school->logo_path" remove-name="remove_images[]" remove-value="logo" hint="PNG with transparent background works best."/>
                <x-form.image name="principal_signature" label="Principal signature" :path="$school->principal_signature_path" remove-name="remove_images[]" remove-value="principal_signature"/>
                <x-form.image name="school_stamp" label="School stamp" :path="$school->school_stamp_path" remove-name="remove_images[]" remove-value="school_stamp"/>
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="form-label" for="f_primary_color">Primary color</label>
                        <input type="color" id="f_primary_color" name="primary_color" value="{{ old('primary_color', $school->primary_color) }}" class="h-9 w-full cursor-pointer rounded border border-slate-300">
                    </div>
                    <div>
                        <label class="form-label" for="f_secondary_color">Secondary color</label>
                        <input type="color" id="f_secondary_color" name="secondary_color" value="{{ old('secondary_color', $school->secondary_color) }}" class="h-9 w-full cursor-pointer rounded border border-slate-300">
                    </div>
                </div>
                <p class="form-hint">Templates using “School primary/secondary” colors follow these automatically.</p>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Numbering formats</h2></div>
            <div class="card-body space-y-3">
                <x-form.input name="student_id_format" label="Student ID numbers" :value="$school->student_id_format" required/>
                <x-form.input name="staff_id_format" label="Staff ID numbers" :value="$school->staff_id_format" required/>
                <x-form.input name="certificate_number_format" label="Certificate numbers" :value="$school->certificate_number_format" required/>
                <p class="form-hint">Tokens: <code>{CODE}</code> school code, <code>{YEAR}</code>, <code>{YY}</code>, <code>{MM}</code>, <code>{SEQ:4}</code> sequence padded to 4 digits (resets yearly when the format has a year). Example: <code>{CODE}/CERT/{YEAR}/{SEQ:5}</code> → BNG/CERT/2026/00001</p>
            </div>
        </div>
    </div>
</div>
