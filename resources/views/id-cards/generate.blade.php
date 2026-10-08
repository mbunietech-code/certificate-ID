@extends('layouts.app')
@php
    $isStaff = $holderType === 'staff';
    $currentYearId = $academicYears->firstWhere('is_current', true)?->id;
@endphp
@section('title', 'Generate ID cards')
@section('header')
    <h1 class="page-title">Generate ID cards</h1>
    <p class="page-subtitle">Select people → choose a template → preview → generate → print.</p>
@endsection
@section('actions')
    <div class="flex gap-1">
        <a href="{{ route('id-cards.generate') }}" class="btn {{ $isStaff ? 'btn-secondary' : 'btn-primary' }}"><x-icon name="students"/> Students</a>
        <a href="{{ route('id-cards.generate', ['holder' => 'staff']) }}" class="btn {{ $isStaff ? 'btn-primary' : 'btn-secondary' }}"><x-icon name="staff"/> Staff</a>
    </div>
@endsection

@section('content')
    @if ($templates->isEmpty())
        <div class="alert alert-warning mb-4">There is no active {{ $isStaff ? 'staff' : 'student' }} ID template. @can('templates.manage')<a href="{{ route('templates.id-cards.create') }}" class="font-semibold underline">Create one</a>@endcan</div>
    @endif

    {{-- Filters (GET) --}}
    <form method="GET" class="card mb-4">
        <input type="hidden" name="holder" value="{{ $holderType }}">
        <input type="hidden" name="template_id" value="{{ old('template_id', $selectedTemplate) }}">
        <div class="card-body flex flex-wrap items-end gap-2">
            <div><label class="form-label">Search</label><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-input w-56" placeholder="Name or number"></div>
            @if ($isStaff)
                <div><label class="form-label">Department</label>
                    <select name="department" class="form-input w-40" data-autosubmit><option value="">All</option>@foreach ($departments as $d)<option @selected(($filters['department'] ?? '') === $d)>{{ $d }}</option>@endforeach</select></div>
                <div><label class="form-label">Status</label>
                    <select name="employment_status" class="form-input w-32" data-autosubmit><option value="">All</option>@foreach (\App\Models\Staff::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['employment_status'] ?? '') === $s)>{{ ucfirst(str_replace('_', ' ', $s)) }}</option>@endforeach</select></div>
            @else
                <div><label class="form-label">Level</label>
                    <select name="level" class="form-input w-32" data-autosubmit><option value="">All</option>@foreach ($levels as $l)<option @selected(($filters['level'] ?? '') === $l)>{{ $l }}</option>@endforeach</select></div>
                <div><label class="form-label">Class</label>
                    <select name="class_name" class="form-input w-32" data-autosubmit><option value="">All</option>@foreach ($classes as $c)<option @selected(($filters['class_name'] ?? '') === $c)>{{ $c }}</option>@endforeach</select></div>
                <div><label class="form-label">Stream</label>
                    <select name="stream" class="form-input w-24" data-autosubmit><option value="">All</option>@foreach ($streams as $s)<option @selected(($filters['stream'] ?? '') === $s)>{{ $s }}</option>@endforeach</select></div>
                <div><label class="form-label">Academic year</label>
                    <select name="academic_year_id" class="form-input w-32" data-autosubmit><option value="">All</option>@foreach ($academicYears as $y)<option value="{{ $y->id }}" @selected(($filters['academic_year_id'] ?? '') == $y->id)>{{ $y->name }}</option>@endforeach</select></div>
                <div><label class="form-label">Status</label>
                    <select name="status" class="form-input w-28" data-autosubmit><option value="">All</option>@foreach (\App\Models\Student::STATUSES as $s)<option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                <div><label class="form-label">ID card</label>
                    <select name="id_status" class="form-input w-36" data-autosubmit>
                        <option value="waiting" @selected(($filters['id_status'] ?? '') === 'waiting')>Not taken yet</option>
                        <option value="taken" @selected(($filters['id_status'] ?? '') === 'taken')>Taken</option>
                        <option value="" @selected(($filters['id_status'] ?? '') === '')>All</option>
                    </select></div>
            @endif
            <div><label class="form-label">Show</label>
                <select name="per_page" class="form-input w-24" data-autosubmit>@foreach ([50, 100, 200, 500] as $n)<option value="{{ $n }}" @selected((int) request('per_page', 100) === $n)>{{ $n }}</option>@endforeach</select></div>
            <button class="btn btn-secondary"><x-icon name="search"/> Apply</button>
        </div>
    </form>

    {{-- Generate (POST) --}}
    <form method="POST" action="{{ route('id-cards.store') }}" class="grid gap-4 xl:grid-cols-[340px_minmax(0,1fr)]">
        @csrf
        <input type="hidden" name="holder_type" value="{{ $holderType }}">
        @foreach (array_filter($filters, fn ($v) => $v !== null && $v !== '') as $key => $value)
            <input type="hidden" name="filters[{{ $key }}]" value="{{ $value }}">
        @endforeach

        <div class="space-y-4">
            <div class="card">
                <div class="card-header"><h2 class="card-title">1. Template</h2></div>
                <div class="card-body space-y-2">
                    @foreach ($templates as $template)
                        <label class="flex cursor-pointer items-start gap-2 rounded-md border border-slate-200 p-2.5 hover:border-blue-400 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                            <input type="radio" name="template_id" value="{{ $template->id }}" class="form-check mt-0.5" required
                                @checked((string) old('template_id', $selectedTemplate ?? $templates->firstWhere('is_default', true)?->id ?? $templates->first()->id) === (string) $template->id)>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-800">{{ $template->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $template->width_mm }} × {{ $template->height_mm }} mm · {{ $template->has_back ? 'two-sided' : 'one-sided' }}{{ $template->isGlobal() ? ' · global' : '' }}</span>
                            </span>
                            <a href="{{ route('templates.id-cards.preview', $template) }}" target="_blank" class="text-xs text-blue-700 hover:underline">view</a>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">2. Options</h2></div>
                <div class="card-body space-y-3">
                    <x-form.select name="academic_year_id" label="Academic year on the card" :options="$academicYears->mapWithKeys(fn ($y) => [$y->id => $y->name.($y->is_current ? ' (current)' : '')])->all()" :value="$currentYearId" placeholder="—" hint="Cards expire at the end of this academic year."/>
                    <fieldset>
                        <legend class="form-label">If a person already has an active card for this year</legend>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="reuse" class="form-check" @checked(old('mode', 'reuse') === 'reuse')> Reprint the existing card (same number)</label>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="mode" value="new" class="form-check" @checked(old('mode') === 'new')> Issue a new card (old one becomes invalid)</label>
                    </fieldset>
                    <fieldset>
                        <legend class="form-label">Print layout</legend>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="layout" value="card" class="form-check" @checked(old('layout', 'card') === 'card')> PVC card printer (one card per page)</label>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="layout" value="sheet" class="form-check" @checked(old('layout') === 'sheet')> Paper sheet (many cards per page)</label>
                    </fieldset>
                    <div class="grid grid-cols-3 gap-2">
                        <x-form.select name="paper" label="Sheet" :options="['A4' => 'A4', 'LETTER' => 'Letter']" :value="setting('default_paper_size')"/>
                        <x-form.input name="margin" type="number" step="0.5" label="Margin mm" value="10"/>
                        <x-form.input name="gap" type="number" step="0.5" label="Gap mm" value="4"/>
                    </div>
                    <fieldset>
                        <legend class="form-label">Card side</legend>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="include_back" value="0" class="form-check" @checked((string) old('include_back', '0') === '0')> Front side only</label>
                        <label class="flex items-center gap-2 text-sm"><input type="radio" name="include_back" value="1" class="form-check" @checked((string) old('include_back') === '1')> Front + back side</label>
                        <p class="form-hint">Use front side only for the first PVC printer test. Back side can be enabled after the artwork is ready.</p>
                    </fieldset>
                    <label class="flex items-center gap-2 text-sm"><input type="hidden" name="crop_marks" value="0"><input type="checkbox" name="crop_marks" value="1" class="form-check" checked> Crop marks on sheets</label>
                </div>
            </div>
        </div>

        <div class="card min-w-0">
            <div class="card-header">
                <h2 class="card-title">3. Select {{ $isStaff ? 'staff' : 'students' }} <span class="font-normal text-slate-500">· <span data-selected-count>0</span> selected</span></h2>
                @if (! $isStaff && ($filters['id_status'] ?? '') === 'waiting')
                    <span class="badge badge-amber">{{ number_format($totalMatching) }} still waiting for their ID</span>
                @endif
                <label class="flex items-center gap-2 rounded bg-blue-50 px-2 py-1 text-sm text-blue-900">
                    <input type="checkbox" name="select_all" value="1" class="form-check"> Select all {{ number_format($totalMatching) }} matching the filters
                </label>
            </div>
            <div class="max-h-[60vh] overflow-auto">
                <table class="table">
                    <thead class="sticky top-0">
                    <tr>
                        <th class="w-8"><input type="checkbox" class="form-check" data-check-all aria-label="Select page"></th>
                        <th>Name</th><th>{{ $isStaff ? 'Employee no.' : 'Adm. no.' }}</th>
                        @if ($isStaff)<th>Job title</th><th>Department</th>@else<th>Level</th><th>Class</th><th>Year</th>@endif
                        <th>Photo</th><th>Current card</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($people as $person)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $person->id }}" class="form-check" data-check-item @checked(in_array($person->id, (array) old('ids', [])))></td>
                            <td class="font-medium text-slate-800">{{ $person->full_name }}</td>
                            <td class="whitespace-nowrap">{{ $isStaff ? $person->employee_number : $person->admission_number }}</td>
                            @if ($isStaff)
                                <td>{{ $person->job_title }}</td><td>{{ $person->department }}</td>
                            @else
                                <td>{{ $person->level }}</td><td class="whitespace-nowrap">{{ $person->classLabel() }}{{ $person->combination ? ' · '.$person->combination : '' }}</td><td>{{ $person->academicYear?->name }}</td>
                            @endif
                            <td>@if ($person->photo_path)<span class="badge badge-green">Yes</span>@else<span class="badge badge-amber" title="A placeholder silhouette will be printed">Missing</span>@endif</td>
                            <td class="whitespace-nowrap text-xs">{{ $currentCards[$person->id] ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty icon="students" title="Nobody matches these filters"/></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $people->links() }}</div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                <span class="mr-auto text-xs text-slate-500">Preview shows up to 6 cards without issuing anything.</span>
                <button class="btn btn-secondary" formaction="{{ route('id-cards.preview') }}" formtarget="_blank" @disabled($templates->isEmpty())><x-icon name="eye"/> Preview</button>
                <button class="btn btn-primary btn-lg" @disabled($templates->isEmpty()) data-confirm="Generate ID cards for the selected people and create a print job?"><x-icon name="printer"/> Generate &amp; print</button>
            </div>
        </div>
    </form>
@endsection
