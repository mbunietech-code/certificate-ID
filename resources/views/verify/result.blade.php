@extends('layouts.guest')
@section('title', 'Verification result')
@section('width', 'max-w-lg')

@section('content')
    @php
        $school = $details['school'] ?? null;
        $label = $type === 'certificate' ? 'certificate' : 'ID card';
        $tone = match ($result) {
            'valid' => ['bg-emerald-600', 'check', 'Valid '.$label, 'This '.$label.' was issued by the school below and is currently valid.'],
            'invalid' => ['bg-red-600', 'ban', 'Not valid', 'This '.$label.' exists but has been revoked, replaced or has expired.'],
            default => ['bg-red-600', 'x', 'Not found', 'No '.$label.' matches this code or number. It may be forged or mistyped.'],
        };
    @endphp
    <div class="card overflow-hidden">
        <div class="{{ $tone[0] }} flex items-center gap-3 px-6 py-5 text-white">
            <span class="grid size-11 place-items-center rounded-full bg-white/20"><x-icon :name="$tone[1]" class="size-6"/></span>
            <div>
                <div class="text-lg font-semibold">{{ $tone[2] }}</div>
                <div class="text-sm text-white/85">{{ $tone[3] }}</div>
            </div>
        </div>
        @if ($details)
            <div class="card-body p-6">
                @if ($school)
                    <div class="mb-4 flex items-center gap-3 border-b border-slate-100 pb-4">
                        @if ($school->logoUrl())<img src="{{ $school->logoUrl() }}" alt="" class="size-12 object-contain">@endif
                        <div class="font-semibold text-slate-900">{{ $school->name }}</div>
                    </div>
                @endif
                <dl>
                    @foreach (collect($details)->except('school') as $key => $value)
                        @if ($value)
                            <div class="dl-row"><dt>{{ $key }}</dt><dd class="font-medium">{{ $value }}</dd></div>
                        @endif
                    @endforeach
                </dl>
                <p class="mt-4 text-xs text-slate-400">Verified {{ now()->format('d/m/Y H:i') }}. Only the details needed to confirm authenticity are shown.</p>
            </div>
        @endif
    </div>
    <p class="mt-4 text-center text-sm"><a href="{{ route('verify.index') }}" class="font-medium text-blue-700 hover:underline">Verify another document</a></p>
@endsection
