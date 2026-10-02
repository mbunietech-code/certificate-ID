@extends('layouts.app')
@section('title', 'Edit '.$student->full_name)
@section('header')<h1 class="page-title">Edit student</h1><p class="page-subtitle">{{ $student->full_name }} · {{ $student->admission_number }}</p>@endsection

@section('content')
    <form method="POST" action="{{ route('students.update', $student) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf @method('PUT')
        @include('students._form')
        <div class="flex justify-end gap-2">
            <a href="{{ route('students.show', $student) }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Save changes</button>
        </div>
    </form>
@endsection
