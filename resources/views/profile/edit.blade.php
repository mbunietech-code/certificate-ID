@extends('layouts.app')
@section('title', 'My profile')
@section('header')<h1 class="page-title">My profile</h1>@endsection

@section('content')
    <form method="POST" action="{{ route('profile.update') }}" class="card max-w-2xl">
        @csrf @method('PUT')
        <div class="card-body grid gap-4 sm:grid-cols-2">
            <x-form.input name="name" label="Full name" :value="$user->name" required/>
            <x-form.input name="email" type="email" label="Email" :value="$user->email" required/>
            <x-form.input name="phone" label="Phone" :value="$user->phone"/>
            <div class="sm:col-span-2 border-t border-slate-100 pt-4">
                <h2 class="card-title">Change password</h2>
                <p class="form-hint">Leave blank to keep your current password. At least 8 characters with letters and numbers.</p>
            </div>
            <x-form.input name="current_password" type="password" label="Current password" autocomplete="current-password"/>
            <div></div>
            <x-form.input name="password" type="password" label="New password" autocomplete="new-password"/>
            <x-form.input name="password_confirmation" type="password" label="Confirm new password" autocomplete="new-password"/>
        </div>
        <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
            <button class="btn btn-primary">Save profile</button>
        </div>
    </form>
@endsection
