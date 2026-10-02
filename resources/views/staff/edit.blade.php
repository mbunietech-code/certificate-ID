@extends('layouts.app')
@section('title', 'Edit '.$member->full_name)
@section('header')<h1 class="page-title">Edit staff member</h1><p class="page-subtitle">{{ $member->full_name }}</p>@endsection

@section('content')
    <form method="POST" action="{{ route('staff.update', $member) }}" enctype="multipart/form-data" class="space-y-4">
        @csrf @method('PUT')
        @include('staff._form')
        <div class="flex justify-end gap-2">
            <a href="{{ route('staff.show', $member) }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Save changes</button>
        </div>
    </form>
@endsection
