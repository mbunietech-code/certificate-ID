@extends('layouts.app')
@section('title', 'Select a school')
@section('header')
    <h1 class="page-title">Select a school</h1>
    <p class="page-subtitle">This action works on one school at a time. Choose the school to continue.</p>
@endsection

@section('content')
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @forelse ($schools as $school)
            <form method="POST" action="{{ route('context.switch') }}">
                @csrf
                <input type="hidden" name="school_id" value="{{ $school->id }}">
                <input type="hidden" name="return" value="{{ $return }}">
                <button class="card flex w-full items-center gap-3 p-4 text-left hover:border-blue-400 hover:shadow">
                    @if ($school->logoUrl())
                        <img src="{{ $school->logoUrl() }}" alt="" class="size-12 object-contain">
                    @else
                        <span class="size-12 rounded-full" style="background: {{ $school->primary_color }}"></span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate font-semibold text-slate-900">{{ $school->name }}</span>
                        <span class="block text-xs text-slate-500">{{ $school->school_code }} · {{ $school->region }}</span>
                    </span>
                    <x-icon name="arrow-right" class="ml-auto size-4 text-slate-400"/>
                </button>
            </form>
        @empty
            <div class="card sm:col-span-2 xl:col-span-3"><x-empty icon="school" title="No active schools">Create a school first.</x-empty></div>
        @endforelse
    </div>
@endsection
