@extends('layouts.app')
@section('title', $student->full_name)
@section('header')
    <div class="flex items-center gap-4">
        @if ($student->photoUrl())
            <img src="{{ $student->photoUrl() }}" alt="" class="size-16 rounded-full object-cover ring-2 ring-white">
        @else
            <span class="grid size-16 place-items-center rounded-full bg-slate-200 text-lg font-semibold text-slate-600">{{ $student->initials() }}</span>
        @endif
        <div>
            <h1 class="page-title">{{ $student->full_name }}</h1>
            <p class="page-subtitle">{{ $student->admission_number }} · {{ $student->classLabel() }} @if ($student->level)· {{ $student->level }}@endif <x-status :value="$student->status" class="ml-1"/></p>
        </div>
    </div>
@endsection
@section('actions')
    @can('id_cards.generate')
        <a href="{{ route('id-cards.generate', ['search' => $student->admission_number, 'status' => '']) }}" class="btn btn-primary"><x-icon name="id-card"/> Generate ID</a>
    @endcan
    @can('certificates.generate')
        <a href="{{ route('certificates.generate', ['search' => $student->admission_number, 'status' => '']) }}" class="btn btn-secondary"><x-icon name="certificate"/> Certificate</a>
    @endcan
    @can('update', $student)<a href="{{ route('students.edit', $student) }}" class="btn btn-secondary"><x-icon name="edit"/> Edit</a>@endcan
    @can('delete', $student)
        <form method="POST" action="{{ route('students.destroy', $student) }}" data-confirm="Delete {{ $student->full_name }}? The record can be restored later.">
            @csrf @method('DELETE') <button class="btn btn-secondary text-red-600"><x-icon name="trash"/> Delete</button>
        </form>
    @endcan
@endsection

@section('content')
    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><h2 class="card-title">Profile</h2></div>
            <dl class="card-body">
                @foreach ([
                    'School' => $student->school->name, 'Gender' => ucfirst($student->gender), 'Date of birth' => format_date($student->date_of_birth),
                    'Nationality' => $student->nationality, 'Level' => $student->level, 'Class' => $student->classLabel(), 'Combination' => $student->combination,
                    'Years' => $student->yearRange(), 'Academic year' => $student->academicYear?->name, 'Parent / guardian' => $student->parent_name,
                    'Parent phone' => $student->parent_phone, 'Student phone' => $student->student_phone, 'Address' => $student->address,
                ] as $label => $value)
                    <div class="dl-row"><dt>{{ $label }}</dt><dd>{{ $value ?: '—' }}</dd></div>
                @endforeach
            </dl>
        </div>

        <div class="space-y-4 xl:col-span-2">
            <div class="card">
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
                                <td class="tabular-nums">{{ $card->print_count }}×</td>
                                <td><x-status :value="$card->status"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="6"><x-empty icon="id-card" title="No ID card issued yet"/></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h2 class="card-title">Certificates</h2></div>
                <div class="overflow-x-auto">
                    <table class="table">
                        <thead><tr><th>Number</th><th>Title</th><th>Programme</th><th>Issued</th><th>Status</th></tr></thead>
                        <tbody>
                        @forelse ($certificates as $certificate)
                            <tr>
                                <td><a href="{{ route('certificates.show', $certificate) }}" class="font-medium text-blue-700 hover:underline">{{ $certificate->certificate_number }}</a></td>
                                <td>{{ $certificate->title }}</td>
                                <td>{{ $certificate->program }}</td>
                                <td>{{ format_date($certificate->issued_on) }}</td>
                                <td><x-status :value="$certificate->status"/></td>
                            </tr>
                        @empty
                            <tr><td colspan="5"><x-empty icon="certificate" title="No certificates issued yet"/></td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
