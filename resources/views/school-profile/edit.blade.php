@extends('layouts.app')
@section('title', 'School profile')
@section('header')
    <h1 class="page-title">School profile</h1>
    <p class="page-subtitle">Details, branding and numbering used on ID cards and certificates of {{ $school->name }}.</p>
@endsection

@section('content')
    <form method="POST" action="{{ route('school-profile.update') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf @method('PUT')
        @include('schools._form', ['profile' => true])
        <div class="flex justify-end"><button class="btn btn-primary">Save profile</button></div>
    </form>
@endsection
