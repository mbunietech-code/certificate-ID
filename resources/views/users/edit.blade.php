@extends('layouts.app')
@section('title', 'Edit user')
@section('header')<h1 class="page-title">Edit user</h1><p class="page-subtitle">{{ $user->email }}</p>@endsection

@section('content')
    <form method="POST" action="{{ route('users.update', $user) }}" class="card max-w-3xl">
        @csrf @method('PUT')
        @include('users._form')
        <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Save changes</button>
        </div>
    </form>
@endsection
