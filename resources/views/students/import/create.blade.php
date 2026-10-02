@extends('layouts.app')
@section('title', 'Import students')
@section('header')
    <h1 class="page-title">Import students</h1>
    <p class="page-subtitle">Upload an Excel or CSV file. Every row is validated and you review a preview before anything is saved.</p>
@endsection
@section('actions')
    <a href="{{ route('students.import.template') }}" class="btn btn-secondary"><x-icon name="download"/> Download sample template</a>
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-3">
        <form method="POST" action="{{ route('students.import.store') }}" enctype="multipart/form-data" class="card xl:col-span-2">
            @csrf
            <div class="card-header"><h2 class="card-title">1. Upload file</h2></div>
            <div class="card-body space-y-4">
                <div>
                    <label class="form-label" for="file">Excel (.xlsx, .xls) or CSV file <span class="text-red-600">*</span></label>
                    <input id="file" type="file" name="file" accept=".xlsx,.xls,.csv" required
                        class="block w-full rounded-md border border-dashed border-slate-300 p-4 text-sm file:mr-3 file:rounded file:border-0 file:bg-blue-700 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-white">
                    <p class="form-hint">Max 10 MB and {{ number_format(\App\Services\StudentImportService::MAX_ROWS) }} rows per file. The first row must contain the column headings.</p>
                </div>
                <fieldset>
                    <legend class="form-label">If an admission number already exists</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="duplicate_mode" value="skip" class="form-check" checked> Skip it (keep existing record)</label>
                        <label class="flex items-center gap-2"><input type="radio" name="duplicate_mode" value="update" class="form-check"> Update it with the file's values</label>
                    </div>
                </fieldset>
            </div>
            <div class="flex justify-end border-t border-slate-200 px-4 py-3">
                <button class="btn btn-primary"><x-icon name="arrow-right"/> Validate &amp; preview</button>
            </div>
        </form>

        <div class="card">
            <div class="card-header"><h2 class="card-title">Columns</h2></div>
            <div class="card-body text-sm text-slate-600">
                <p class="mb-2">Required: <strong>Admission Number, First Name, Last Name, Gender, Class</strong>.</p>
                <p class="mb-2">Optional: Middle Name, Date of Birth, Level (O-Level / A-Level), Stream, Combination, Entry Year, Completion Year, Academic Year, Nationality, Parent Name, Parent Phone, Student Phone, Address.</p>
                <p>Gender accepts M/F/Male/Female. Dates accept YYYY-MM-DD or DD/MM/YYYY. A blank academic year uses the current one.</p>
            </div>
        </div>
    </div>

    <div class="card mt-4">
        <div class="card-header"><h2 class="card-title">Recent imports</h2></div>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>File</th><th>By</th><th>Rows</th><th>Result</th><th>Status</th><th>Date</th></tr></thead>
                <tbody>
                @forelse ($recent as $import)
                    <tr>
                        <td>@if ($import->status === 'preview')<a href="{{ route('students.import.show', $import) }}" class="font-medium text-blue-700 hover:underline">{{ $import->original_name }}</a>@else {{ $import->original_name }} @endif</td>
                        <td>{{ $import->user?->name }}</td>
                        <td class="tabular-nums">{{ $import->summary['total'] ?? 0 }}</td>
                        <td class="text-xs text-slate-500">
                            @if ($r = $import->summary['result'] ?? null)
                                {{ $r['created'] }} created · {{ $r['updated'] }} updated · {{ $r['skipped'] }} skipped · {{ $r['invalid'] }} invalid
                            @else
                                {{ $import->summary['valid'] ?? 0 }} valid · {{ $import->summary['duplicate'] ?? 0 }} duplicate · {{ $import->summary['invalid'] ?? 0 }} invalid
                            @endif
                        </td>
                        <td><x-status :value="$import->status"/></td>
                        <td class="text-slate-500">{{ $import->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6"><x-empty icon="upload" title="No imports yet"/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
