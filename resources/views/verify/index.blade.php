@extends('layouts.guest')
@section('title', 'Verify a document')
@section('width', 'max-w-md')

@section('content')
    <div class="card">
        <div class="card-body p-6">
            <h1 class="text-lg font-semibold text-slate-900">Verify an ID card or certificate</h1>
            <p class="mb-5 text-sm text-slate-500">Scan the QR code on the document, or enter the number printed on it.</p>
            <form method="POST" action="{{ route('verify.lookup') }}" class="space-y-4">
                @csrf
                <fieldset class="flex gap-4 text-sm">
                    <label class="flex items-center gap-2"><input type="radio" name="type" value="certificate" class="form-check" @checked(old('type', 'certificate') === 'certificate')> Certificate</label>
                    <label class="flex items-center gap-2"><input type="radio" name="type" value="id" class="form-check" @checked(old('type') === 'id')> ID card</label>
                </fieldset>
                <x-form.input name="number" label="Document number" required placeholder="e.g. BWM/CERT/2026/00001" maxlength="60"/>
                <button class="btn btn-primary w-full"><x-icon name="search"/> Verify</button>
            </form>
        </div>
    </div>
@endsection
