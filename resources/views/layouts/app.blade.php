@php
    $user = auth()->user();
    $nav = fn (string $pattern) => request()->routeIs($pattern) ? 'nav-link active' : 'nav-link';
    $currentYear = $activeSchool?->currentAcademicYear?->name;
@endphp
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') · {{ setting('system_name') }}</title>
    <x-favicon/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="flex min-h-full">
    {{-- Sidebar --}}
    <aside id="sidebar" class="fixed inset-y-0 left-0 z-30 hidden w-60 shrink-0 flex-col overflow-y-auto bg-slate-900 px-3 pb-6 lg:flex">
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5 px-2 py-4">
            @if (setting('system_logo'))
                <img src="{{ Storage::disk('public')->url(setting('system_logo')) }}" alt="" class="size-8 rounded object-contain bg-white p-0.5">
            @else
                <span class="grid size-8 place-items-center rounded bg-blue-600 text-white"><x-icon name="id-card" class="size-5"/></span>
            @endif
            <span class="text-sm leading-tight font-semibold text-white">{{ setting('system_name') }}</span>
        </a>

        <nav class="flex flex-col gap-0.5">
            <a href="{{ route('dashboard') }}" class="{{ $nav('dashboard') }}"><x-icon name="dashboard"/> Dashboard</a>

            @can('schools.view')
                <div class="nav-section">Schools</div>
                <a href="{{ route('schools.index') }}" class="{{ $nav('schools.index') }}"><x-icon name="school"/> All Schools</a>
                @can('schools.manage')
                    <a href="{{ route('schools.create') }}" class="{{ $nav('schools.create') }}"><x-icon name="plus"/> Add School</a>
                @endcan
            @endcan

            @canany(['students.view', 'staff.view'])
                <div class="nav-section">People</div>
            @endcanany
            @can('students.view')
                <a href="{{ route('students.index') }}" class="{{ $nav('students.index') }}"><x-icon name="students"/> All Students</a>
            @endcan
            @can('students.create')
                <a href="{{ route('students.create') }}" class="{{ $nav('students.create') }}"><x-icon name="plus"/> Add Student</a>
            @endcan
            @can('students.import')
                <a href="{{ route('students.import') }}" class="{{ $nav('students.import*') }}"><x-icon name="upload"/> Import Students</a>
            @endcan
            @can('staff.view')
                <a href="{{ route('staff.index') }}" class="{{ $nav('staff.index') }}"><x-icon name="staff"/> All Staff</a>
            @endcan
            @can('staff.manage')
                <a href="{{ route('staff.create') }}" class="{{ $nav('staff.create') }}"><x-icon name="plus"/> Add Staff</a>
            @endcan

            @canany(['templates.view', 'id_cards.view'])
                <div class="nav-section">ID Cards</div>
            @endcanany
            @can('templates.view')
                <a href="{{ route('templates.id-cards.index') }}" class="{{ $nav('templates.id-cards.*') }}"><x-icon name="template"/> ID Templates</a>
            @endcan
            @can('id_cards.generate')
                <a href="{{ route('id-cards.generate') }}" class="{{ $nav('id-cards.generate') }}"><x-icon name="id-card"/> Generate IDs</a>
            @endcan
            @can('id_cards.view')
                <a href="{{ route('id-cards.index') }}" class="{{ $nav('id-cards.index') }}"><x-icon name="list"/> Issued IDs</a>
            @endcan

            @canany(['templates.view', 'certificates.view'])
                <div class="nav-section">Certificates</div>
            @endcanany
            @can('templates.view')
                <a href="{{ route('templates.certificates.index') }}" class="{{ $nav('templates.certificates.*') }}"><x-icon name="template"/> Certificate Templates</a>
            @endcan
            @can('certificates.generate')
                <a href="{{ route('certificates.generate') }}" class="{{ $nav('certificates.generate') }}"><x-icon name="certificate"/> Generate Certificates</a>
            @endcan
            @can('certificates.view')
                <a href="{{ route('certificates.index') }}" class="{{ $nav('certificates.index') }}"><x-icon name="list"/> Issued Certificates</a>
            @endcan

            @can('print.view')
                <div class="nav-section">Printing</div>
                <a href="{{ route('print.index') }}" class="{{ $nav('print.index') }} {{ request()->routeIs('print.show') ? 'active' : '' }}"><x-icon name="printer"/> Print Center</a>
                <a href="{{ route('print.history') }}" class="{{ $nav('print.history') }}"><x-icon name="history"/> Print History</a>
            @endcan

            <div class="nav-section">Administration</div>
            @can('academic_years.view')
                <a href="{{ route('academic-years.index') }}" class="{{ $nav('academic-years.*') }}"><x-icon name="calendar"/> Academic Years</a>
            @endcan
            @can('reports.view')
                <a href="{{ route('reports.index') }}" class="{{ $nav('reports.*') }}"><x-icon name="chart"/> Reports</a>
            @endcan
            @can('users.view')
                <a href="{{ route('users.index') }}" class="{{ $nav('users.*') }}"><x-icon name="user"/> Users</a>
            @endcan
            @can('roles.manage')
                <a href="{{ route('roles.index') }}" class="{{ $nav('roles.*') }}"><x-icon name="shield"/> Roles &amp; Permissions</a>
            @endcan
            @if ($activeSchool)
                @can('updateProfile', $activeSchool)
                    <a href="{{ route('school-profile.edit') }}" class="{{ $nav('school-profile.*') }}"><x-icon name="school"/> School Profile</a>
                @endcan
            @endif
            @can('settings.system')
                <a href="{{ route('settings.edit') }}" class="{{ $nav('settings.*') }}"><x-icon name="cog"/> System Settings</a>
            @endcan
            @can('audit.view')
                <a href="{{ route('audit.index') }}" class="{{ $nav('audit.*') }}"><x-icon name="list"/> Audit Logs</a>
            @endcan
        </nav>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col lg:pl-60">
        {{-- Header: school context --}}
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-slate-200 bg-white px-4">
            <button type="button" class="btn btn-ghost lg:hidden" data-menu-toggle="mobile-nav" aria-label="Menu"><x-icon name="menu" class="size-5"/></button>

            <div class="flex min-w-0 items-center gap-3">
                @if ($activeSchool)
                    @if ($activeSchool->logoUrl())
                        <img src="{{ $activeSchool->logoUrl() }}" alt="" class="size-9 shrink-0 object-contain">
                    @else
                        <span class="size-9 shrink-0 rounded-full" style="background: {{ $activeSchool->primary_color }}"></span>
                    @endif
                    <div class="min-w-0 leading-tight">
                        <div class="truncate text-sm font-semibold text-slate-900">{{ $activeSchool->name }}</div>
                        <div class="text-xs text-slate-500">
                            {{ $activeSchool->school_code }}
                            @if ($currentYear) · Academic Year: <span class="font-medium text-slate-700">{{ $currentYear }}</span>@endif
                        </div>
                    </div>
                @else
                    <div class="leading-tight">
                        <div class="text-sm font-semibold text-slate-900">All Schools</div>
                        <div class="text-xs text-slate-500">System-wide view</div>
                    </div>
                @endif
            </div>

            <div class="ml-auto flex items-center gap-2">
                @if ($user->isSuperAdmin())
                    <form method="POST" action="{{ route('context.switch') }}" class="hidden sm:block">
                        @csrf
                        <input type="hidden" name="return" value="{{ request()->fullUrl() }}">
                        <label for="school-switch" class="sr-only">Active school</label>
                        <select id="school-switch" name="school_id" class="form-input form-input-sm w-60" data-autosubmit>
                            <option value="">All Schools</option>
                            @foreach ($switchableSchools as $s)
                                <option value="{{ $s->id }}" @selected($activeSchool?->id === $s->id)>{{ $s->name }}{{ $s->status !== 'active' ? ' (inactive)' : '' }}</option>
                            @endforeach
                        </select>
                    </form>
                @endif

                <div class="relative">
                    <button type="button" class="btn btn-ghost" data-menu-toggle="user-menu">
                        <span class="grid size-7 place-items-center rounded-full bg-slate-200 text-xs font-semibold text-slate-700">{{ mb_strtoupper(mb_substr($user->name, 0, 1)) }}</span>
                        <span class="hidden text-left leading-tight md:block">
                            <span class="block text-sm font-medium text-slate-800">{{ $user->name }}</span>
                            <span class="block text-xs text-slate-500">{{ $user->role->name }}</span>
                        </span>
                        <x-icon name="chevron-down"/>
                    </button>
                    <div id="user-menu" data-menu hidden class="absolute right-0 mt-1 w-48 rounded-md border border-slate-200 bg-white py-1 shadow-lg">
                        <a href="{{ route('profile.edit') }}" class="flex items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50"><x-icon name="user"/> My profile</a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-slate-50"><x-icon name="logout"/> Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        {{-- Mobile navigation (reuses the sidebar markup) --}}
        <div id="mobile-nav" data-menu hidden class="border-b border-slate-200 bg-slate-900 p-3 lg:hidden">
            <a href="{{ route('dashboard') }}" class="nav-link">Dashboard</a>
            @can('students.view')<a href="{{ route('students.index') }}" class="nav-link">Students</a>@endcan
            @can('staff.view')<a href="{{ route('staff.index') }}" class="nav-link">Staff</a>@endcan
            @can('id_cards.generate')<a href="{{ route('id-cards.generate') }}" class="nav-link">Generate IDs</a>@endcan
            @can('certificates.generate')<a href="{{ route('certificates.generate') }}" class="nav-link">Generate Certificates</a>@endcan
            @can('print.view')<a href="{{ route('print.index') }}" class="nav-link">Print Center</a>@endcan
            @if ($user->isSuperAdmin())
                <form method="POST" action="{{ route('context.switch') }}" class="mt-2">
                    @csrf
                    <input type="hidden" name="return" value="{{ request()->fullUrl() }}">
                    <select name="school_id" class="form-input form-input-sm" data-autosubmit>
                        <option value="">All Schools</option>
                        @foreach ($switchableSchools as $s)
                            <option value="{{ $s->id }}" @selected($activeSchool?->id === $s->id)>{{ $s->name }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
        </div>

        <main class="mx-auto w-full max-w-[1400px] flex-1 p-4 lg:p-6">
            @foreach (['success' => 'alert-success', 'error' => 'alert-error', 'info' => 'alert-info', 'warning' => 'alert-warning'] as $key => $class)
                @if (session($key))
                    <div class="alert {{ $class }} mb-4" role="alert">
                        <span class="flex-1">{{ session($key) }}</span>
                        <button type="button" data-dismiss class="opacity-60 hover:opacity-100" aria-label="Dismiss"><x-icon name="x"/></button>
                    </div>
                @endif
            @endforeach
            @if ($errors->any() && ! View::hasSection('suppress_error_summary'))
                <div class="alert alert-error mb-4" role="alert">
                    <div class="flex-1">
                        <div class="font-medium">Please correct the following:</div>
                        <ul class="mt-1 list-disc pl-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @hasSection('header')
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                    <div>@yield('header')</div>
                    <div class="flex flex-wrap items-center gap-2">@yield('actions')</div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</div>
@stack('scripts')
</body>
</html>
