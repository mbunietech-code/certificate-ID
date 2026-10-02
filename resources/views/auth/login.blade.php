@extends('layouts.guest')
@section('title', 'Sign in')

@section('content')
    <div class="card">
        <div class="card-body p-6">
            <h1 class="text-lg font-semibold text-slate-900">Sign in</h1>
            <p class="mb-5 text-sm text-slate-500">Use the account given to you by your administrator.</p>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf
                <x-form.input name="email" type="email" label="Email address" required autofocus autocomplete="username"/>
                <x-form.input name="password" type="password" label="Password" required autocomplete="current-password"/>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" value="1" class="form-check"> Keep me signed in
                </label>
                <button class="btn btn-primary w-full">Sign in</button>
            </form>
        </div>
    </div>
    <p class="mt-4 text-center text-sm text-slate-500">
        Verifying a card or certificate? <a href="{{ route('verify.index') }}" class="font-medium text-blue-700 hover:underline">Open the verification page</a>
    </p>
@endsection
