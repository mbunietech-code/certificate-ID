@extends('layouts.app')
@section('title', 'Issued ID cards')
@section('header')
    <h1 class="page-title">Issued ID cards</h1>
    <p class="page-subtitle">{{ number_format($cards->total()) }} card(s). Select cards to reprint them without changing their numbers.</p>
@endsection
@section('actions')
    @can('id_cards.generate')<a href="{{ route('id-cards.generate') }}" class="btn btn-primary"><x-icon name="plus"/> Generate IDs</a>@endcan
@endsection

@section('content')
    <div class="card">
        <form method="GET" class="card-header">
            <div class="flex flex-wrap items-center gap-2">
                <input type="search" name="search" value="{{ request('search') }}" placeholder="Card no., name or adm. no." class="form-input w-60">
                <select name="holder_type" class="form-input w-32" data-autosubmit>
                    <option value="">Students &amp; staff</option>
                    <option value="student" @selected(request('holder_type') === 'student')>Students</option>
                    <option value="staff" @selected(request('holder_type') === 'staff')>Staff</option>
                </select>
                <select name="status" class="form-input w-32" data-autosubmit>
                    <option value="">All statuses</option>
                    @foreach (\App\Models\IdCard::STATUSES as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach
                </select>
                <select name="academic_year_id" class="form-input w-32" data-autosubmit>
                    <option value="">All years</option>
                    @foreach ($academicYears as $y)<option value="{{ $y->id }}" @selected(request('academic_year_id') == $y->id)>{{ $y->name }}</option>@endforeach
                </select>
                <button class="btn btn-secondary"><x-icon name="search"/> Filter</button>
            </div>
        </form>

        <form method="POST" action="{{ route('id-cards.reprint') }}">
            @csrf
            @can('print.execute')
                @unless (tenant()->isAllSchools())
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm">
                        <span class="text-slate-500"><span data-selected-count>0</span> selected</span>
                        <select name="template_id" class="form-input form-input-sm w-56">
                            <option value="">Same template as issued</option>
                            @foreach ($templates as $t)<option value="{{ $t->id }}">{{ $t->name }}</option>@endforeach
                        </select>
                        <select name="layout" class="form-input form-input-sm w-44">
                            <option value="card">PVC card printer</option>
                            <option value="sheet">A4 sheet</option>
                        </select>
                        <input type="hidden" name="include_back" value="1"><input type="hidden" name="crop_marks" value="1">
                        <button class="btn btn-primary btn-sm"><x-icon name="printer"/> Reprint selected</button>
                    </div>
                @endunless
            @endcan
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th class="w-8"><input type="checkbox" class="form-check" data-check-all></th><th>Card number</th><th>Holder</th><th>Type</th>@if (tenant()->isAllSchools())<th>School</th>@endif<th>Template</th><th>Year</th><th>Issued</th><th>Expires</th><th>Printed</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($cards as $card)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $card->id }}" class="form-check" data-check-item></td>
                            <td><a href="{{ route('id-cards.show', $card) }}" class="font-medium whitespace-nowrap text-blue-700 hover:underline">{{ $card->card_number }}</a></td>
                            <td>{{ $card->holder?->full_name }}</td>
                            <td>{{ $card->holderKind() }}</td>
                            @if (tenant()->isAllSchools())<td>{{ $card->school->school_code }}</td>@endif
                            <td class="max-w-48 truncate">{{ $card->template?->name }}</td>
                            <td>{{ $card->academicYear?->name }}</td>
                            <td class="whitespace-nowrap">{{ format_date($card->issued_at) }}</td>
                            <td class="whitespace-nowrap">{{ format_date($card->expires_at) }}</td>
                            <td class="tabular-nums">{{ $card->print_count }}×</td>
                            <td><x-status :value="$card->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><x-empty icon="id-card" title="No ID cards issued yet"/></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        <div class="border-t border-slate-200 px-4 py-3">{{ $cards->links() }}</div>
    </div>
@endsection
