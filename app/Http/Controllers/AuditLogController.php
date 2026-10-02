<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public const FILTERS = ['school_id', 'user_id', 'action', 'entity_type', 'date_from', 'date_to'];

    public function index(Request $request): View
    {
        $this->authorize('audit.view');

        $user = $request->user();
        $filters = $request->only(self::FILTERS);
        // Audit logs are not tenant-scoped by a global scope (login events have no
        // school), so restrict school users explicitly here.
        $schoolId = $user->isSuperAdmin() ? ($this->tenant()->scopedSchoolId() ?? ($filters['school_id'] ?? null)) : $user->school_id;
        $filters['school_id'] = $schoolId;

        $logs = AuditLog::query()->filter($filters)
            ->with(['user:id,name,email', 'school:id,school_code'])
            ->latest('id')
            ->paginate(50)->withQueryString();

        return view('audit.index', [
            'logs' => $logs,
            'filters' => $filters,
            'users' => User::visibleTo($user, $schoolId ? (int) $schoolId : null)->orderBy('name')->get(['id', 'name']),
            'schools' => $user->isSuperAdmin() ? School::orderBy('name')->get(['id', 'name']) : collect(),
            'actions' => AuditLog::query()->when($schoolId, fn ($q) => $q->where('school_id', $schoolId))
                ->select('action')->distinct()->orderBy('action')->limit(200)->pluck('action'),
        ]);
    }
}
