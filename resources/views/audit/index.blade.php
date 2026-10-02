@extends('layouts.app')
@section('title', 'Audit logs')
@section('header')
    <h1 class="page-title">Audit logs</h1>
    <p class="page-subtitle">Who did what, when and from where.</p>
@endsection

@section('content')
    <div class="card">
        <form method="GET" class="card-header">
            <div class="flex flex-wrap items-end gap-2">
                @if ($schools->isNotEmpty() && tenant()->isAllSchools())
                    <div><label class="form-label">School</label>
                        <select name="school_id" class="form-input w-48"><option value="">All</option>@foreach ($schools as $s)<option value="{{ $s->id }}" @selected(($filters['school_id'] ?? '') == $s->id)>{{ $s->name }}</option>@endforeach</select></div>
                @endif
                <div><label class="form-label">User</label>
                    <select name="user_id" class="form-input w-40"><option value="">All</option>@foreach ($users as $u)<option value="{{ $u->id }}" @selected(($filters['user_id'] ?? '') == $u->id)>{{ $u->name }}</option>@endforeach</select></div>
                <div><label class="form-label">Action</label>
                    <select name="action" class="form-input w-52"><option value="">All</option>@foreach ($actions as $a)<option @selected(($filters['action'] ?? '') === $a)>{{ $a }}</option>@endforeach</select></div>
                <div><label class="form-label">From</label><input type="date" name="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-input"></div>
                <div><label class="form-label">To</label><input type="date" name="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-input"></div>
                <button class="btn btn-secondary"><x-icon name="search"/> Filter</button>
            </div>
        </form>
        <div class="overflow-x-auto">
            <table class="table">
                <thead><tr><th>Time</th><th>User</th><th>School</th><th>Action</th><th>Entity</th><th>Description</th><th>IP address</th></tr></thead>
                <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="whitespace-nowrap text-slate-500" title="{{ $log->created_at }}">{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                        <td class="whitespace-nowrap">{{ $log->user?->name ?? '—' }}</td>
                        <td>{{ $log->school?->school_code ?? '—' }}</td>
                        <td><span class="badge {{ str_starts_with($log->action, 'auth.failed') ? 'badge-red' : (str_contains($log->action, 'delete') || str_contains($log->action, 'revoke') ? 'badge-amber' : 'badge-slate') }} font-mono">{{ $log->action }}</span></td>
                        <td class="whitespace-nowrap text-xs text-slate-500">{{ $log->entity_type }}{{ $log->entity_id ? ' #'.$log->entity_id : '' }}</td>
                        <td>{{ $log->description }}</td>
                        <td class="font-mono text-xs">{{ $log->ip_address }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7"><x-empty icon="list" title="No audit entries"/></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="border-t border-slate-200 px-4 py-3">{{ $logs->links() }}</div>
    </div>
@endsection
