@extends('layouts.app')
@section('title', 'Edit '.$school->name)
@section('header')<h1 class="page-title">Edit school</h1><p class="page-subtitle">{{ $school->name }}</p>@endsection

@section('content')
    <form method="POST" action="{{ route('schools.update', $school) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf @method('PUT')
        @include('schools._form')
        <div class="flex justify-end gap-2">
            <a href="{{ route('schools.show', $school) }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Save changes</button>
        </div>
    </form>
@endsection
