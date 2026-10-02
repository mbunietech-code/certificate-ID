@extends('layouts.app')
@php
    $currentYearId = $academicYears->firstWhere('is_current', true)?->id;
    $defaultTemplate = $templates->firstWhere('id', (int) old('template_id', $selectedTemplate)) ?? $templates->first();
@endphp
@section('title', 'Generate certificates')
@section('header')
    <h1 class="page-title">Generate certificates</h1>
    <p class="page-subtitle">Choose a template → enter the details → select students → preview → generate → print.</p>
@endsection

@section('content')
    @if ($templates->isEmpty())
        <div class="alert alert-warning mb-4">There is no active certificate template. @can('templates.manage')<a href="{{ route('templates.certificates.create') }}" class="font-semibold underline">Create one</a>@endcan</div>
    @endif

    <form method="GET" class="card mb-4">
        <div class="card-body flex flex-wrap items-end gap-2">
            <div><label class="form-label">Search</label><input type="search" name="search" value="{{ $filters['search'] ?? '' }}" class="form-input w-56" placeholder="Name or admission no."></div>
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
            <div><label class="form-label">Show</label>
                <select name="per_page" class="form-input w-24" data-autosubmit>@foreach ([50, 100, 200, 500] as $n)<option value="{{ $n }}" @selected((int) request('per_page', 100) === $n)>{{ $n }}</option>@endforeach</select></div>
            <button class="btn btn-secondary"><x-icon name="search"/> Apply</button>
        </div>
    </form>

    <form method="POST" action="{{ route('certificates.store') }}" class="grid gap-4 xl:grid-cols-[380px_minmax(0,1fr)]">
        @csrf
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
                                data-title="{{ $template->default_title }}" data-body="{{ $template->default_body }}"
                                @checked($defaultTemplate?->id === $template->id)>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-800">{{ $template->name }}</span>
                                <span class="block text-xs text-slate-500">{{ $template->sizeLabel() }}{{ $template->isGlobal() ? ' · global' : '' }}</span>
                            </span>
                            <a href="{{ route('templates.certificates.preview', $template) }}" target="_blank" class="text-xs text-blue-700 hover:underline">view</a>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">2. Certificate details</h2></div>
                <div class="card-body space-y-3">
                    <x-form.input name="title" label="Certificate title" :value="$defaultTemplate?->default_title ?? 'Certificate of Completion'" required/>
                    <x-form.input name="program" label="Course / event / programme" placeholder="e.g. Certificate of Secondary Education Examination 2026"/>
                    <x-form.textarea name="description" label="Description / body text" :value="$defaultTemplate?->default_body" rows="3"/>
                    <div class="grid grid-cols-2 gap-3">
                        <x-form.input name="issued_on" type="date" label="Date of issue" :value="now()->toDateString()" required/>
                        <x-form.select name="academic_year_id" label="Academic year" :options="$academicYears->mapWithKeys(fn ($y) => [$y->id => $y->name])->all()" :value="$currentYearId" placeholder="—"/>
                    </div>
                    <p class="form-hint">Each certificate gets a unique number ({{ tenant()->school()->certificate_number_format }}) and a QR code for verification.</p>
                </div>
            </div>
        </div>

        <div class="card min-w-0">
            <div class="card-header">
                <h2 class="card-title">3. Select students <span class="font-normal text-slate-500">· <span data-selected-count>0</span> selected</span></h2>
                <label class="flex items-center gap-2 rounded bg-blue-50 px-2 py-1 text-sm text-blue-900">
                    <input type="checkbox" name="select_all" value="1" class="form-check"> Select all {{ number_format($students->total()) }} matching the filters
                </label>
            </div>
            <div class="max-h-[60vh] overflow-auto">
                <table class="table">
                    <thead class="sticky top-0"><tr><th class="w-8"><input type="checkbox" class="form-check" data-check-all aria-label="Select page"></th><th>Name</th><th>Adm. no.</th><th>Level</th><th>Class</th><th>Year</th></tr></thead>
                    <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $student->id }}" class="form-check" data-check-item @checked(in_array($student->id, (array) old('ids', [])))></td>
                            <td class="font-medium text-slate-800">{{ $student->full_name }}</td>
                            <td>{{ $student->admission_number }}</td>
                            <td>{{ $student->level }}</td>
                            <td>{{ $student->classLabel() }}{{ $student->combination ? ' · '.$student->combination : '' }}</td>
                            <td>{{ $student->academicYear?->name }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="students" title="No students match these filters"/></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-200 px-4 py-3">{{ $students->links() }}</div>
            <div class="flex flex-wrap items-center justify-end gap-2 border-t border-slate-200 bg-slate-50 px-4 py-3">
                <span class="mr-auto text-xs text-slate-500">Preview shows up to 4 certificates without issuing numbers.</span>
                <button class="btn btn-secondary" formaction="{{ route('certificates.preview') }}" formtarget="_blank" @disabled($templates->isEmpty())><x-icon name="eye"/> Preview</button>
                <button class="btn btn-primary btn-lg" @disabled($templates->isEmpty()) data-confirm="Issue certificates (with permanent numbers) for the selected students?"><x-icon name="printer"/> Generate &amp; print</button>
            </div>
        </div>
    </form>
@endsection

@push('scripts')
<script>
    // Switching template pre-fills its default title/body (only if the user hasn't typed something else).
    document.querySelectorAll('input[name="template_id"]').forEach((radio) => radio.addEventListener('change', () => {
        const title = document.getElementById('f_title');
        const body = document.getElementById('f_description');
        if (radio.dataset.title) title.value = radio.dataset.title;
        if (radio.dataset.body) body.value = radio.dataset.body;
    }));
</script>
@endpush
