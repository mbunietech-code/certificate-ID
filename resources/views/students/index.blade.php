@extends('layouts.app')
@section('title', 'Students')
@section('header')
    <h1 class="page-title">Students</h1>
    <p class="page-subtitle">{{ number_format($students->total()) }} student(s) match the current filters</p>
@endsection
@section('actions')
    @can('students.export')
        <div class="relative">
            <button type="button" class="btn btn-secondary" data-menu-toggle="export-menu"><x-icon name="download"/> Export <x-icon name="chevron-down"/></button>
            <div id="export-menu" data-menu hidden class="absolute right-0 z-10 mt-1 w-44 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                {{-- Tick students in the list to export only them; otherwise everyone matching the filters. --}}
                <p class="border-b border-slate-100 px-3 pb-1.5 pt-1 text-xs text-slate-500" data-export-scope="All {{ number_format($students->total()) }} matching">All {{ number_format($students->total()) }} matching</p>
                @foreach (['csv' => 'CSV', 'xlsx' => 'Excel (.xlsx)', 'pdf' => 'PDF'] as $format => $label)
                    <a href="{{ route('students.export', request()->except(['page', 'per_page', 'ids']) + ['format' => $format]) }}" class="block px-3 py-1.5 text-sm hover:bg-slate-50" data-export-selected>{{ $label }}</a>
                @endforeach
            </div>
        </div>
    @endcan
    @can('students.import')<a href="{{ route('students.import') }}" class="btn btn-secondary"><x-icon name="upload"/> Import</a>@endcan
    @can('students.create')<a href="{{ route('students.create') }}" class="btn btn-primary"><x-icon name="plus"/> Add student</a>@endcan
@endsection

@section('content')
    @php
        $idStatus = $filters['id_status'] ?? '';
        $tabQuery = fn (string $status) => array_filter(request()->except(['page', 'id_status']) + ['id_status' => $status]);
        $tabs = [
            'waiting' => ['Waiting for ID', $idCounts['waiting'], 'Not yet taken (printed or not)'],
            'taken' => ['ID taken', $idCounts['taken'], 'Card collected by the student'],
            '' => ['All students', $idCounts['waiting'] + $idCounts['taken'], null],
        ];
    @endphp
    <div class="mb-3 flex flex-wrap gap-1">
        @foreach ($tabs as $key => [$label, $count, $title])
            <a href="{{ route('students.index', $tabQuery($key)) }}" title="{{ $title }}"
               class="btn btn-sm {{ $idStatus === $key ? 'btn-primary' : 'btn-secondary' }}">
                {{ $label }} <span class="rounded-full px-1.5 text-xs tabular-nums {{ $idStatus === $key ? 'bg-white/20' : 'bg-slate-100' }}">{{ number_format($count) }}</span>
            </a>
        @endforeach
    </div>

    <div class="card">
        <form method="GET" class="card-header">
            <input type="hidden" name="id_status" value="{{ $idStatus }}">
            <div class="flex flex-wrap items-center gap-2">
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Name or admission no." class="form-input w-56">
                <select name="level" class="form-input w-32" data-autosubmit>
                    <option value="">All levels</option>
                    @foreach ($levels as $level)<option @selected(($filters['level'] ?? '') === $level)>{{ $level }}</option>@endforeach
                </select>
                <select name="class_name" class="form-input w-32" data-autosubmit>
                    <option value="">All classes</option>
                    @foreach ($classes as $class)<option @selected(($filters['class_name'] ?? '') === $class)>{{ $class }}</option>@endforeach
                </select>
                <select name="stream" class="form-input w-28" data-autosubmit>
                    <option value="">All streams</option>
                    @foreach ($streams as $stream)<option @selected(($filters['stream'] ?? '') === $stream)>{{ $stream }}</option>@endforeach
                </select>
                <select name="gender" class="form-input w-28" data-autosubmit>
                    <option value="">Gender</option>
                    <option value="male" @selected(($filters['gender'] ?? '') === 'male')>Male</option>
                    <option value="female" @selected(($filters['gender'] ?? '') === 'female')>Female</option>
                </select>
                <select name="academic_year_id" class="form-input w-32" data-autosubmit>
                    <option value="">All years</option>
                    @foreach ($academicYears as $year)<option value="{{ $year->id }}" @selected(($filters['academic_year_id'] ?? '') == $year->id)>{{ $year->name }}</option>@endforeach
                </select>
                <select name="status" class="form-input w-32" data-autosubmit>
                    <option value="">All statuses</option>
                    @foreach (\App\Models\Student::STATUSES as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ ucfirst($status) }}</option>@endforeach
                    <option value="deleted" @selected(($filters['status'] ?? '') === 'deleted')>Deleted</option>
                </select>
                <select name="per_page" class="form-input w-24" data-autosubmit>
                    @foreach ([25, 50, 100] as $n)<option value="{{ $n }}" @selected((int) request('per_page', 25) === $n)>{{ $n }}/page</option>@endforeach
                </select>
                <button class="btn btn-secondary"><x-icon name="search"/> Filter</button>
                @if (array_filter($filters))<a href="{{ route('students.index') }}" class="btn btn-ghost">Clear</a>@endif
            </div>
        </form>

        <form method="POST" action="{{ route('students.bulk') }}" data-confirm="Apply this action to the selected students?">
            @csrf
            @canany(['students.update', 'students.delete', 'print.execute'])
                <div class="flex flex-wrap items-center gap-2 border-b border-slate-200 bg-slate-50 px-4 py-2 text-sm">
                    <span class="text-slate-500"><span data-selected-count>0</span> selected</span>
                    <select name="action" class="form-input form-input-sm w-48" required>
                        <option value="">Bulk action…</option>
                        @canany(['students.update', 'print.execute'])
                            <option value="id_taken">✓ Mark ID taken</option>
                            <option value="id_waiting">Back to waiting for ID</option>
                        @endcanany
                        @can('students.update')
                            <option value="activate">Mark active</option>
                            <option value="deactivate">Deactivate</option>
                            <option value="graduate">Mark graduated</option>
                        @endcan
                        @can('students.delete')<option value="delete">Delete</option>@endcan
                    </select>
                    <button class="btn btn-secondary btn-sm">Apply</button>
                    @can('id_cards.generate')
                        <a href="{{ route('id-cards.generate', request()->only(\App\Http\Controllers\StudentController::FILTERS)) }}" class="btn btn-primary btn-sm ml-auto"><x-icon name="id-card"/> Generate IDs for this list</a>
                    @endcan
                </div>
            @endcanany
            <div class="overflow-x-auto">
                <table class="table">
                    <thead>
                    <tr>
                        <th class="w-8"><input type="checkbox" class="form-check" data-check-all aria-label="Select all"></th>
                        <th>Student</th><th>Adm. No.</th>@if (tenant()->isAllSchools())<th>School</th>@endif<th>Level</th><th>Class</th><th>Gender</th><th>Year</th><th>Status</th><th>ID card</th><th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($students as $student)
                        <tr>
                            <td><input type="checkbox" name="ids[]" value="{{ $student->id }}" class="form-check" data-check-item></td>
                            <td>
                                <div class="flex items-center gap-2.5">
                                    @if ($student->photoUrl())
                                        <img src="{{ $student->photoUrl() }}" alt="" class="size-8 rounded-full object-cover">
                                    @else
                                        <span class="grid size-8 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-600">{{ $student->initials() }}</span>
                                    @endif
                                    @if ($student->trashed())
                                        <span class="text-slate-500 line-through">{{ $student->full_name }}</span>
                                    @else
                                        <a href="{{ route('students.show', $student) }}" class="font-medium text-slate-900 hover:text-blue-700">{{ $student->full_name }}</a>
                                    @endif
                                </div>
                            </td>
                            <td class="whitespace-nowrap">{{ $student->admission_number }}</td>
                            @if (tenant()->isAllSchools())<td>{{ $student->school->school_code }}</td>@endif
                            <td>{{ $student->level }}</td>
                            <td class="whitespace-nowrap">{{ $student->classLabel() }}{{ $student->combination ? ' · '.$student->combination : '' }}</td>
                            <td>{{ ucfirst($student->gender) }}</td>
                            <td>{{ $student->academicYear?->name }}</td>
                            <td><x-status :value="$student->trashed() ? 'deleted' : $student->status"/></td>
                            <td class="whitespace-nowrap">
                                @if ($student->hasTakenId())
                                    <span class="badge badge-green" title="Taken {{ $student->id_taken_at->format('d/m/Y H:i') }}{{ $student->idTakenBy ? ' · marked by '.$student->idTakenBy->name : '' }}">✓ Taken {{ $student->id_taken_at->format('d/m') }}</span>
                                    @can('markIdTaken', $student)
                                        <button form="id-waiting-{{ $student->id }}" class="ml-1 text-xs text-slate-500 hover:text-slate-800 hover:underline" title="Put back in the waiting list">Undo</button>
                                    @endcan
                                @else
                                    @if ($student->printed_cards_count > 0)
                                        <span class="badge badge-blue" title="ID card printed – ready to hand over">Printed</span>
                                    @else
                                        <span class="badge badge-amber">Not printed</span>
                                    @endif
                                    @can('markIdTaken', $student)
                                        <button form="id-taken-{{ $student->id }}" class="btn btn-success btn-sm ml-1" title="The student has collected the ID card">✓ Taken</button>
                                    @endcan
                                @endif
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @if ($student->trashed())
                                    @can('students.delete')
                                        <button form="restore-{{ $student->id }}" class="btn btn-ghost btn-sm">Restore</button>
                                    @endcan
                                @else
                                    @can('update', $student)<a href="{{ route('students.edit', $student) }}" class="btn btn-ghost btn-sm" title="Edit"><x-icon name="edit"/></a>@endcan
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11"><x-empty icon="students" title="No students found">Adjust the filters, add a student or import a list from Excel.</x-empty></td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </form>
        @foreach ($students->getCollection()->filter->trashed() as $student)
            <form id="restore-{{ $student->id }}" method="POST" action="{{ route('students.restore', $student->id) }}" hidden>@csrf</form>
        @endforeach
        {{-- "Taken" / "Undo" buttons submit these (forms cannot be nested inside the bulk form). --}}
        @foreach ($students->getCollection()->reject->trashed() as $student)
            <form id="id-taken-{{ $student->id }}" method="POST" action="{{ route('students.id-taken', $student) }}" hidden>@csrf<input type="hidden" name="taken" value="1"></form>
            <form id="id-waiting-{{ $student->id }}" method="POST" action="{{ route('students.id-taken', $student) }}" hidden>@csrf<input type="hidden" name="taken" value="0"></form>
        @endforeach
        <div class="border-t border-slate-200 px-4 py-3">{{ $students->links() }}</div>
    </div>
@endsection
