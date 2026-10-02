@php
    $schoolId = $student->school_id ?? tenant()->scopedSchoolId();
    $yearOptions = $academicYears->where('school_id', $schoolId)->mapWithKeys(fn ($y) => [$y->id => $y->name.($y->is_current ? ' (current)' : '')])->all();
@endphp
<div class="grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-header"><h2 class="card-title">Student details</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <x-form.input name="admission_number" label="Admission number" :value="$student->admission_number" required/>
            <x-form.select name="status" label="Status" :options="collect(\App\Models\Student::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])->all()" :value="$student->status" required/>
            <x-form.select name="academic_year_id" label="Academic year" :options="$yearOptions" :value="$student->academic_year_id" placeholder="—"/>
            <x-form.input name="first_name" label="First name" :value="$student->first_name" required/>
            <x-form.input name="middle_name" label="Middle name" :value="$student->middle_name"/>
            <x-form.input name="last_name" label="Last name" :value="$student->last_name" required/>
            <x-form.select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female']" :value="$student->gender" placeholder="Select…" required/>
            <x-form.input name="date_of_birth" type="date" label="Date of birth" :value="$student->date_of_birth?->format('Y-m-d')"/>
            <x-form.input name="nationality" label="Nationality" :value="$student->nationality"/>
        </div>
        <div class="card-header border-t"><h2 class="card-title">Class</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <div>
                <x-form.input name="level" label="Level" :value="$student->level" list="levels-list" hint="e.g. O-Level or A-Level"/>
                <datalist id="levels-list">@foreach ($levels as $l)<option value="{{ $l }}">@endforeach</datalist>
            </div>
            <div>
                <x-form.input name="class_name" label="Class / form" :value="$student->class_name" list="classes-list" required/>
                <datalist id="classes-list">@foreach ($classes as $c)<option value="{{ $c }}">@endforeach</datalist>
            </div>
            <div>
                <x-form.input name="stream" label="Stream" :value="$student->stream" list="streams-list"/>
                <datalist id="streams-list">@foreach ($streams as $s)<option value="{{ $s }}">@endforeach</datalist>
            </div>
            <x-form.input name="combination" label="Combination" :value="$student->combination" hint="A-Level subject combination, e.g. PCM"/>
            <x-form.input name="entry_year" type="number" label="Entry year" :value="$student->entry_year" min="1950" max="2100"/>
            <x-form.input name="completion_year" type="number" label="Completion year" :value="$student->completion_year" min="1950" max="2100" hint="Shown as the year range on ID cards."/>
        </div>
        <div class="card-header border-t"><h2 class="card-title">Contacts</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <x-form.input name="parent_name" label="Parent / guardian" :value="$student->parent_name"/>
            <x-form.input name="parent_phone" label="Parent phone" :value="$student->parent_phone"/>
            <x-form.input name="student_phone" label="Student phone" :value="$student->student_phone"/>
            <x-form.input name="address" label="Address" :value="$student->address" class="sm:col-span-3"/>
        </div>
    </div>
    <div class="card self-start">
        <div class="card-header"><h2 class="card-title">Photo</h2></div>
        <div class="card-body">
            <x-form.image name="photo" label="Passport photo" :path="$student->photo_path" remove-name="remove_photo" round hint="JPG/PNG, max 4 MB. Resized automatically; a square, well-lit head-and-shoulders photo prints best."/>
        </div>
    </div>
</div>
