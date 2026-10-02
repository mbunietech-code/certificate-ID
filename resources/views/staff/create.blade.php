@extends('layouts.app')
@section('title', 'Add staff')
@section('header')<h1 class="page-title">Add staff member</h1>@endsection

@section('content')
    <form method="POST" action="{{ route('staff.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @include('staff._form')
        <div class="flex justify-end gap-2">
            <a href="{{ route('staff.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Save</button>
        </div>
    </form>
@endsection
