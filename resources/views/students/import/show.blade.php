@extends('layouts.app')
@section('title', 'Import preview')
@php
    $s = $import->summary;
    $willImport = $s['valid'] + ($import->duplicate_mode === 'update' ? $s['duplicate'] : 0);
@endphp
@section('header')
    <h1 class="page-title">Import preview</h1>
    <p class="page-subtitle">{{ $import->original_name }} · duplicates will be <strong>{{ $import->duplicate_mode === 'update' ? 'updated' : 'skipped' }}</strong></p>
@endsection

@section('content')
    <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
        <a href="?show=all" class="stat {{ $filter === 'all' ? 'ring-2 ring-blue-500' : '' }}"><div class="stat-label">Rows in file</div><div class="stat-value">{{ $s['total'] }}</div></a>
        <a href="?show=valid" class="stat {{ $filter === 'valid' ? 'ring-2 ring-blue-500' : '' }}"><div class="stat-label text-emerald-700">Valid – new</div><div class="stat-value text-emerald-700">{{ $s['valid'] }}</div></a>
        <a href="?show=duplicate" class="stat {{ $filter === 'duplicate' ? 'ring-2 ring-blue-500' : '' }}"><div class="stat-label text-amber-700">Already exist</div><div class="stat-value text-amber-700">{{ $s['duplicate'] }}</div></a>
        <a href="?show=invalid" class="stat {{ $filter === 'invalid' ? 'ring-2 ring-blue-500' : '' }}"><div class="stat-label text-red-700">Invalid – not imported</div><div class="stat-value text-red-700">{{ $s['invalid'] }}</div></a>
    </div>

    @if ($s['invalid'] > 0)
        <div class="alert alert-warning mt-4">
            <x-icon name="ban" class="mt-0.5 size-4 shrink-0"/>
            <span><strong>{{ $s['invalid'] }} row(s) contain errors and will NOT be imported.</strong> Fix them in the file and import them again, or continue to import only the valid rows.</span>
        </div>
    @endif

    <div class="card mt-4">
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Row</th><th>Status</th><th>Adm. No.</th><th>Name</th><th>Gender</th><th>Level</th><th>Class</th><th>Stream / comb.</th><th>DOB</th><th>Errors</th></tr></thead>
                <tbody>
                @forelse ($rows as $row)
                    @php $d = $row['data']; @endphp
                    <tr class="{{ $row['status'] === 'invalid' ? 'bg-red-50/50' : '' }}">
                        <td class="tabular-nums text-slate-500">{{ $row['row'] }}</td>
                        <td><x-status :value="$row['status']"/></td>
                        <td>{{ $d['admission_number'] ?? '' }}</td>
                        <td>{{ trim(($d['first_name'] ?? '').' '.($d['middle_name'] ?? '').' '.($d['last_name'] ?? '')) }}</td>
                        <td>{{ ucfirst($d['gender'] ?? '') }}</td>
                        <td>{{ $d['level'] ?? '' }}</td>
                        <td>{{ $d['class_name'] ?? '' }}</td>
                        <td>{{ trim(($d['stream'] ?? '').' '.($d['combination'] ?? '')) }}</td>
                        <td>{{ $d['date_of_birth'] ?? '' }}</td>
                        <td class="text-xs text-red-700">{{ implode(' ', $row['errors']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="10"><x-empty title="No rows in this view"/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if ($truncated)<p class="border-t border-slate-200 px-4 py-2 text-xs text-slate-500">Showing the first 1,000 rows of this view.</p>@endif
    </div>

    @if ($import->status === 'preview')
        <div class="mt-4 flex flex-wrap items-center justify-end gap-2">
            <form method="POST" action="{{ route('students.import.cancel', $import) }}">
                @csrf @method('DELETE') <button class="btn btn-secondary">Cancel import</button>
            </form>
            <form method="POST" action="{{ route('students.import.confirm', $import) }}" data-confirm="Import {{ $willImport }} row(s){{ $s['invalid'] ? ' and skip '.$s['invalid'].' invalid row(s)' : '' }}?">
                @csrf
                <button class="btn btn-success" @disabled($willImport === 0)><x-icon name="check"/> Confirm import of {{ $willImport }} row(s)</button>
            </form>
        </div>
    @endif
@endsection
