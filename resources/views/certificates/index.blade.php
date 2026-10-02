@extends('layouts.app')
@section('title', 'Issued certificates')
@section('header')
    <h1 class="page-title">Issued certificates</h1>
    <p class="page-subtitle">{{ number_format($certificates->total()) }} certificate(s)</p>
@endsection
@section('actions')
    @can('certificates.generate')<a href="{{ route('certificates.generate') }}" class="btn btn-primary"><x-icon name="plus"/> Generate certificates</a>@endcan
@endsection

@section('content')
    <div class="card">
        <form method="GET" class="card-header">
            <div class="flex flex-wrap items-center gap-2">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Number, name, title, programme" class="form-input w-64">
                <select name="template_id" class="form-input w-48" data-autosubmit>
                    <option value="">All templates</option>
                    @foreach ($templates as $t)<option value="{{ $t->id }}" @selected(request('template_id') == $t->id)>{{ $t->name }}</option>@endforeach
                </select>
                <select name="status" class="form-input w-28" data-autosubmit>
                    <option value="">All statuses</option>
                    <option value="valid" @selected(request('status') === 'valid')>Valid</option>
                    <option value="revoked" @selected(request('status') === 'revoked')>Revoked</option>
                </select>
                <select name="academic_year_id" class="form-input w-32" data-autosubmit>
                    <option value="">All years</option>
                    @foreach ($academicYears as $y)<option value="{{ $y->id }}" @selected(request('academic_year_id') == $y->id)>{{ $y->name }}</option>@endforeach
                </select>
                <button class="btn btn-secondary"><x-icon name="search"/> Filter</button>
            </div>
        </form>
        <form method="POST" action="{{ route('certificates.reprint') }}">
            @csrf
            @can('print.execute')
                @unless (tenant()->isAllSchools())
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm">
                        <span class="text-slate-500"><span data-selected-count>0</span> selected</span>
                        <button class="btn btn-primary btn-sm"><x-icon name="printer"/> Reprint selected</button>
                    </div>
                @endunless
            @endcan
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th class="w-8"><input type="checkbox" class="form-check" data-check-all></th><th>Number</th><th>Recipient</th>@if (tenant()->isAllSchools())<th>School</th>@endif<th>Title</th><th>Programme</th><th>Issued</th><th>Printed</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($certificates as $certificate)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $certificate->id }}" class="form-check" data-check-item></td>
                            <td><a href="{{ route('certificates.show', $certificate) }}" class="font-medium whitespace-nowrap text-blue-700 hover:underline">{{ $certificate->certificate_number }}</a></td>
                            <td>{{ $certificate->recipient_name }}<div class="text-xs text-slate-500">{{ $certificate->student?->admission_number }}</div></td>
                            @if (tenant()->isAllSchools())<td>{{ $certificate->school->school_code }}</td>@endif
                            <td>{{ $certificate->title }}</td>
                            <td class="max-w-56 truncate">{{ $certificate->program }}</td>
                            <td class="whitespace-nowrap">{{ format_date($certificate->issued_on) }}</td>
                            <td class="tabular-nums">{{ $certificate->print_count }}×</td>
                            <td><x-status :value="$certificate->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="9"><x-empty icon="certificate" title="No certificates issued yet"/></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        <div class="border-t border-slate-200 px-4 py-3">{{ $certificates->links() }}</div>
    </div>
@endsection
