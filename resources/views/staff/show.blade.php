@extends('layouts.app')
@section('title', $member->full_name)
@section('header')
    <div class="flex items-center gap-4">
        @if ($member->photoUrl())
            <img src="{{ $member->photoUrl() }}" alt="" class="size-16 rounded-full object-cover">
        @else
            <span class="grid size-16 place-items-center rounded-full bg-slate-200 text-lg font-semibold text-slate-600">{{ $member->initials() }}</span>
        @endif
        <div>
            <h1 class="page-title">{{ $member->full_name }}</h1>
            <p class="page-subtitle">{{ $member->employee_number }} · {{ $member->job_title }} <x-status :value="$member->employment_status" class="ml-1"/></p>
        </div>
    </div>
@endsection
@section('actions')
    @can('id_cards.generate')
        <a href="{{ route('id-cards.generate', ['holder' => 'staff', 'search' => $member->employee_number, 'employment_status' => '']) }}" class="btn btn-primary"><x-icon name="id-card"/> Generate ID</a>
    @endcan
    @can('update', $member)<a href="{{ route('staff.edit', $member) }}" class="btn btn-secondary"><x-icon name="edit"/> Edit</a>@endcan
    @can('delete', $member)
        <form method="POST" action="{{ route('staff.destroy', $member) }}" data-confirm="Delete {{ $member->full_name }}?">
            @csrf @method('DELETE') <button class="btn btn-secondary text-red-600"><x-icon name="trash"/> Delete</button>
        </form>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Profile</h2></div>
            <dl class="card-body">
                @foreach (['School' => $member->school->name, 'Gender' => ucfirst($member->gender), 'Date of birth' => format_date($member->date_of_birth),
                           'Department' => $member->department, 'Phone' => $member->phone, 'Email' => $member->email] as $label => $value)
                    <div class="dl-row"><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                @endforeach
            </dl>
        </div>
        <div class="card xl:col-span-2">
            <div class="card-header"><h2 class="card-title">ID cards</h2></div>
            <div class="overflow-x-auto">
                <table class="table">
                    <thead><tr><th>Card number</th><th>Template</th><th>Issued</th><th>Expires</th><th>Printed</th><th>Status</th></tr></thead>
                    <tbody>
                    @forelse ($idCards as $card)
                        <tr>
                            <td><a href="{{ route('id-cards.show', $card) }}" class="font-medium text-blue-700 hover:underline">{{ $card->card_number }}</a></td>
                            <td>{{ $card->template?->name }}</td>
                            <td>{{ format_date($card->issued_at) }}</td>
                            <td>{{ format_date($card->expires_at) }}</td>
                            <td>{{ $card->print_count }}×</td>
                            <td><x-status :value="$card->status"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty icon="id-card" title="No ID card issued yet"/></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
