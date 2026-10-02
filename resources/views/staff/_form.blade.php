<div class="grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-header"><h2 class="card-title">Staff details</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <x-form.input name="employee_number" label="Employee number" :value="$member->employee_number" required/>
            <x-form.select name="employment_status" label="Employment status" :options="collect(\App\Models\Staff::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst(str_replace('_', ' ', $s))])->all()" :value="$member->employment_status" required/>
            <x-form.select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female']" :value="$member->gender" placeholder="Select…" required/>
            <x-form.input name="first_name" label="First name" :value="$member->first_name" required/>
            <x-form.input name="middle_name" label="Middle name" :value="$member->middle_name"/>
            <x-form.input name="last_name" label="Last name" :value="$member->last_name" required/>
            <x-form.input name="date_of_birth" type="date" label="Date of birth" :value="$member->date_of_birth?->format('Y-m-d')"/>
            <x-form.input name="job_title" label="Job title" :value="$member->job_title"/>
            <div>
                <x-form.input name="department" label="Department" :value="$member->department" list="departments-list"/>
                <datalist id="departments-list">@foreach ($departments as $d)<option value="{{ $d }}">@endforeach</datalist>
            </div>
            <x-form.input name="phone" label="Phone" :value="$member->phone"/>
            <x-form.input name="email" type="email" label="Email" :value="$member->email" class="sm:col-span-2"/>
        </div>
    </div>
    <div class="card self-start">
        <div class="card-header"><h2 class="card-title">Photo</h2></div>
        <div class="card-body">
            <x-form.image name="photo" label="Passport photo" :path="$member->photo_path" remove-name="remove_photo" round/>
        </div>
    </div>
</div>
