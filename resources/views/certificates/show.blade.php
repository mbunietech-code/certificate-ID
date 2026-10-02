@extends('layouts.app')
@section('title', 'Certificate '.$certificate->certificate_number)
@section('header')
    <h1 class="page-title">Certificate {{ $certificate->certificate_number }}</h1>
    <p class="page-subtitle">{{ $certificate->recipient_name }} · {{ $certificate->title }} <x-status :value="$certificate->status" class="ml-1"/></p>
@endsection
@section('actions')
    <a href="{{ $certificate->verificationUrl() }}" target="_blank" class="btn btn-secondary"><x-icon name="qr"/> Verification page</a>
    @if ($certificate->isValid())
        @can('print.execute')
            <form method="POST" action="{{ route('certificates.reprint') }}">
                @csrf <input type="hidden" name="ids[]" value="{{ $certificate->id }}">
                <button class="btn btn-primary"><x-icon name="printer"/> Reprint</button>
            </form>
        @endcan
    @endif
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">Details</h2></div>
            <dl class="card-body">
                @foreach ([
                    'School' => $certificate->school->name,
                    'Recipient' => $certificate->recipient_name,
                    'Admission no.' => $certificate->student?->admission_number,
                    'Title' => $certificate->title,
                    'Programme' => $certificate->program,
                    'Description' => $certificate->description,
                    'Academic year' => $certificate->academicYear?->name,
                    'Date of issue' => format_date($certificate->issued_on),
                    'Issued by' => $certificate->issuer?->name,
                    'Template' => $certificate->template?->name,
                    'Times printed' => (string) $certificate->print_count,
                    'Verification code' => $certificate->verification_code,
                    'Revocation reason' => $certificate->revoked_reason,
                ] as $label => $value)
                    @if ($value !== null && $value !== '')<div class="dl-row"><dt>{{ $label }}</dt><dd class="break-all">{{ $value }}</dd></div>@endif
                @endforeach
            </dl>
        </div>
        @can('revoke', $certificate)
            @if ($certificate->isValid())
                <form method="POST" action="{{ route('certificates.revoke', $certificate) }}" class="card self-start" data-confirm="Revoke this certificate? Verification will show it as revoked.">
                    @csrf
                    <div class="card-header"><h2 class="card-title text-red-700">Revoke certificate</h2></div>
                    <div class="card-body space-y-3">
                        <x-form.input name="reason" label="Reason" required maxlength="255"/>
                        <button class="btn btn-danger"><x-icon name="ban"/> Revoke</button>
                    </div>
                </form>
            @endif
        @endcan
    </div>
@endsection
