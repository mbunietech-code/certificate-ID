@php
    $schoolId = $student->school_id ?? tenant()->scopedSchoolId();
    $school = $schoolId ? \App\Models\School::withoutGlobalScope('school')->find($schoolId) : null;
    $isBenja = (bool) $school?->usesBenjaStudentForm();
    $noAdmissionNumber = \App\Models\Student::NO_ADMISSION_NUMBER;
    $yearOptions = $academicYears->where('school_id', $schoolId)->mapWithKeys(fn ($y) => [$y->id => $y->name.($y->is_current ? ' (current)' : '')])->all();
    $levelOptions = collect(\App\Models\Student::LEVELS)->mapWithKeys(fn ($level) => [$level => $level])->all();
    $aLevelClassOptions = collect(\App\Models\Student::A_LEVEL_CLASSES)->mapWithKeys(fn ($class) => [$class => $class])->all();
    $combinationOptions = collect(\App\Models\Student::A_LEVEL_COMBINATIONS)->mapWithKeys(fn ($combination) => [$combination => $combination])->all();
@endphp
<div class="grid gap-4 xl:grid-cols-3">
    <div class="card xl:col-span-2">
        <div class="card-header"><h2 class="card-title">Student details</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-3">
            <div>
                <label for="f_admission_number" class="form-label">Admission number</label>
                <div class="flex gap-2">
                    <input type="text" id="f_admission_number" name="admission_number" value="{{ old('admission_number', $student->admission_number) }}" maxlength="40"
                        placeholder="{{ $noAdmissionNumber }}" class="form-input min-w-0 flex-1 @error('admission_number') border-red-500 @enderror">
                    <button type="button" class="btn btn-ghost btn-sm shrink-0" title="Student has no admission number"
                        onclick="const field = document.getElementById('f_admission_number'); field.value = '{{ $noAdmissionNumber }}'; field.focus();">Use {{ $noAdmissionNumber }}</button>
                </div>
                <p class="form-hint">No admission number? Click “Use {{ $noAdmissionNumber }}” (or leave it blank).</p>
                @error('admission_number')<p class="form-error">{{ $message }}</p>@enderror
            </div>
            <x-form.select name="status" label="Status" :options="collect(\App\Models\Student::STATUSES)->mapWithKeys(fn ($s) => [$s => ucfirst($s)])->all()" :value="$student->status" required/>
            <x-form.select name="academic_year_id" label="Academic year" :options="$yearOptions" :value="$student->academic_year_id" placeholder="—"/>
            <x-form.input name="first_name" label="First name" :value="$student->first_name" required/>
            <x-form.input name="middle_name" label="Middle name" :value="$student->middle_name"/>
            <x-form.input name="last_name" label="Last name" :value="$student->last_name" required/>
            <x-form.select name="gender" label="Gender" :options="['male' => 'Male', 'female' => 'Female']" :value="$student->gender" placeholder="Select…" required/>
            @if (! $isBenja)
                <x-form.input name="date_of_birth" type="date" label="Date of birth" :value="$student->date_of_birth?->format('Y-m-d')"/>
                <x-form.input name="nationality" label="Nationality" :value="$student->nationality"/>
            @endif
        </div>
        <div class="card-header border-t"><h2 class="card-title">Class</h2></div>
        <div class="card-body grid gap-4 sm:grid-cols-3" @if ($isBenja) data-benja-class-form @endif>
            @if ($isBenja)
                <x-form.select name="level" label="Level" :options="$levelOptions" :value="$student->level" placeholder="Select level…" required data-level-select/>
                <x-form.select name="class_name" label="Class / form" :options="$aLevelClassOptions" :value="$student->level === 'A-Level' ? $student->class_name : null" placeholder="Select class…" data-class-select/>
                <div data-olevel-class-note>
                    <span class="form-label">Class / form</span>
                    <div class="form-input bg-slate-50 text-slate-600">{{ \App\Models\Student::O_LEVEL_CLASS }}</div>
                    <p class="form-hint">Set automatically for O-Level (printed on the ID card).</p>
                </div>
                <x-form.select name="combination" label="Combination" :options="$combinationOptions" :value="$student->combination" placeholder="Select combination…" data-combination-field/>
                <input type="hidden" name="stream" value="">
            @else
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
            @endif
            <x-form.input name="entry_year" type="number" label="Entry year" :value="$student->entry_year" min="1950" max="2100"/>
            <x-form.input name="completion_year" type="number" label="Completion year" :value="$student->completion_year" min="1950" max="2100" hint="Shown as the year range on ID cards."/>
        </div>
        @if (! $isBenja)
            <div class="card-header border-t"><h2 class="card-title">Contacts</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <x-form.input name="parent_name" label="Parent / guardian" :value="$student->parent_name"/>
                <x-form.input name="parent_phone" label="Parent phone" :value="$student->parent_phone"/>
                <x-form.input name="student_phone" label="Student phone" :value="$student->student_phone"/>
                <x-form.input name="address" label="Address" :value="$student->address" class="sm:col-span-3"/>
            </div>
        @endif
    </div>
    <div class="card self-start">
        <div class="card-header"><h2 class="card-title">Photo</h2></div>
        <div class="card-body">
            <x-form.image name="photo" label="Passport photo" :path="$student->photo_path" remove-name="remove_photo" round camera hint="Upload JPG/PNG or use the camera. The photo is cropped and resized automatically to 455 × 488 px for the ID card."/>
        </div>
    </div>
</div>

@if ($isBenja)
    @once
        @push('scripts')
            <script>
                document.querySelectorAll('[data-benja-class-form]').forEach((form) => {
                    const level = form.querySelector('[data-level-select]');
                    const klass = form.querySelector('[data-class-select]');
                    const combination = form.querySelector('[data-combination-field]');
                    const oLevelNote = form.querySelector('[data-olevel-class-note]');

                    // O-Level: no class choice (always "Form I - IV"). A-Level: class (Form V / VI) + combination.
                    const refresh = () => {
                        const isALevel = level.value === 'A-Level';
                        for (const field of [klass, combination]) {
                            field.closest('div').hidden = !isALevel;
                            field.required = isALevel;
                            if (!isALevel) {
                                field.value = '';
                            }
                        }
                        oLevelNote.hidden = level.value !== 'O-Level';
                    };

                    level.addEventListener('change', refresh);
                    refresh();
                });
            </script>
        @endpush
    @endonce
@endif
