@extends('layouts.app')
@section('title', $year->exists ? 'Edit academic year' : 'Add academic year')
@section('header')<h1 class="page-title">{{ $year->exists ? 'Edit academic year '.$year->name : 'Add academic year' }}</h1>@endsection

@section('content')
    <form method="POST" action="{{ $year->exists ? route('academic-years.update', $year) : route('academic-years.store') }}" class="card max-w-xl">
        @csrf @if ($year->exists) @method('PUT') @endif
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <x-form.input name="name" label="Name" :value="$year->name" required hint="e.g. 2026 or 2025/2026" class="sm:col-span-2"/>
            <x-form.input name="start_date" type="date" label="Start date" :value="$year->start_date?->format('Y-m-d')"/>
            <x-form.input name="end_date" type="date" label="End date" :value="$year->end_date?->format('Y-m-d')" hint="ID cards issued for this year expire on this date."/>
            <x-form.select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" :value="$year->status ?? 'active'" required/>
            <label class="flex items-center gap-2 self-end pb-2 text-sm"><input type="hidden" name="is_current" value="0"><input type="checkbox" name="is_current" value="1" class="form-check" @checked(old('is_current', $year->is_current))> Current academic year</label>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
            <a href="{{ route('academic-years.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Save</button>
        </div>
    </form>
@endsection
