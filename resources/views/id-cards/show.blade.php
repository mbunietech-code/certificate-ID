@extends('layouts.app')
@section('title', 'ID card '.$card->card_number)
@section('header')
    <h1 class="page-title">ID card {{ $card->card_number }}</h1>
    <p class="page-subtitle">{{ $card->holder?->full_name }} · {{ $card->holderKind() }} <x-status :value="$card->isValid() ? $card->status : ($card->status === 'active' ? 'expired' : $card->status)" class="ml-1"/></p>
@endsection
@section('actions')
    <a href="{{ $card->verificationUrl() }}" target="_blank" class="btn btn-secondary"><x-icon name="qr"/> Verification page</a>
    <a href="{{ route('id-cards.front-png', $card) }}" class="btn btn-secondary"><x-icon name="download"/> Download front PNG</a>
    @if ($card->status === 'active')
        @can('print.execute')
            <form method="POST" action="{{ route('id-cards.reprint') }}">
                @csrf <input type="hidden" name="ids[]" value="{{ $card->id }}"><input type="hidden" name="layout" value="card"><input type="hidden" name="include_back" value="0">
                <button class="btn btn-primary"><x-icon name="printer"/> Reprint front</button>
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
                    'School' => $card->school->name,
                    'Holder' => $card->holder?->full_name,
                    $card->holder_type === 'staff' ? 'Employee no.' : 'Admission no.' => $card->holder_type === 'staff' ? $card->holder?->employee_number : $card->holder?->admission_number,
                    'Template' => $card->template?->name,
                    'Academic year' => $card->academicYear?->name,
                    'Issued' => format_date($card->issued_at).' by '.($card->issuer?->name ?? '—'),
                    'Valid until' => format_date($card->expires_at),
                    'Times printed' => $card->print_count.($card->last_printed_at ? ' (last '.$card->last_printed_at->diffForHumans().')' : ''),
                    'Verification code' => $card->verification_code,
                    'Revocation reason' => $card->revoked_reason,
                ] as $label => $value)
                    @if ($value !== null)<div class="dl-row"><dt>{{ $label }}</dt><dd class="break-all">{{ $value }}</dd></div>@endif
                @endforeach
            </dl>
        </div>
        @can('revoke', $card)
            @if ($card->status === 'active')
                <form method="POST" action="{{ route('id-cards.revoke', $card) }}" class="card self-start" data-confirm="Revoke this card? Its QR code will show as invalid.">
                    @csrf
                    <div class="card-header"><h2 class="card-title text-red-700">Revoke card</h2></div>
                    <div class="card-body space-y-3">
                        <p class="text-sm text-slate-600">Use when a card is lost, stolen or issued in error.</p>
                        <x-form.input name="reason" label="Reason" required maxlength="255"/>
                        <button class="btn btn-danger"><x-icon name="ban"/> Revoke</button>
                    </div>
                </form>
            @endif
        @endcan
    </div>
@endsection
