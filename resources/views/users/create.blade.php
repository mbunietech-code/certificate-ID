@extends('layouts.app')
@section('title', 'Add user')
@section('header')<h1 class="page-title">Add user</h1>@endsection

@section('content')
    <form method="POST" action="{{ route('users.store') }}" class="card max-w-3xl">
        @csrf
        @include('users._form')
        <div class="flex justify-end gap-2 border-t border-slate-200 px-4 py-3">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
            <button class="btn btn-primary">Create user</button>
        </div>
    </form>
@endsection
