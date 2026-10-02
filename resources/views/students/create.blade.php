@extends('layouts.app')
@section('title', 'Add student')
@section('header')<h1 class="page-title">Add student</h1>@endsection

@section('content')
    <form method="POST" action="{{ route('students.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @include('students._form')
        <div class="flex justify-end gap-2">
            <a href="{{ route('students.index') }}" class="btn btn-secondary">Cancel</a>
            <button name="add_another" value="1" class="btn btn-secondary">Save &amp; add another</button>
            <button class="btn btn-primary">Save student</button>
        </div>
    </form>
@endsection
