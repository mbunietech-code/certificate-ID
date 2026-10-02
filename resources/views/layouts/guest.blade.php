<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ setting('system_name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full font-sans text-slate-800 antialiased">
<div class="flex min-h-full flex-col items-center justify-center px-4 py-10">
    <div class="mb-6 flex items-center gap-2.5">
        @if (setting('system_logo'))
            <img src="{{ Storage::disk('public')->url(setting('system_logo')) }}" alt="" class="size-10 object-contain">
        @else
            <span class="grid size-10 place-items-center rounded-lg bg-blue-700 text-white"><x-icon name="id-card" class="size-6"/></span>
        @endif
        <div class="leading-tight">
            <div class="font-semibold text-slate-900">{{ setting('system_name') }}</div>
            @if (setting('organization_name'))<div class="text-xs text-slate-500">{{ setting('organization_name') }}</div>@endif
        </div>
    </div>
    <div class="w-full @yield('width', 'max-w-sm')">
        @yield('content')
    </div>
    <p class="mt-8 text-xs text-slate-400">&copy; {{ now()->year }} {{ setting('organization_name') ?: setting('system_name') }}</p>
</div>
</body>
</html>
