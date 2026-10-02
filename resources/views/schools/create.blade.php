@extends('layouts.app')
@section('title', 'Add school')
@section('header')
    <h1 class="page-title">Add school</h1>
    <p class="page-subtitle">A current academic year is created automatically. You can also create the school administrator account now.</p>
@endsection

@section('content')
    <form method="POST" action="{{ route('schools.store') }}" enctype="multipart/form-data" class="space-y-4">
        @csrf
        @include('schools._form')

        <div class="card">
            <div class="card-header"><h2 class="card-title">School administrator (optional)</h2></div>
            <div class="card-body grid gap-4 sm:grid-cols-3">
                <x-form.input name="admin_name" label="Name"/>
                <x-form.input name="admin_email" type="email" label="Email"/>
                <x-form.input name="admin_password" type="password" label="Password" hint="Min. 8 characters, letters and numbers." autocomplete="new-password"/>
            </div>
        </div>

        <div class="flex justify-end gap-2">
            <a href="{{ route('schools.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Create school</button>
        </div>
    </form>
@endsection
