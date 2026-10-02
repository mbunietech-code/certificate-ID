<?php

namespace App\Http\Controllers;

use App\Models\Staff;
use App\Services\ImageService;
use App\Services\TableExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StaffController extends Controller implements HasMiddleware
{
    public const FILTERS = ['search', 'department', 'gender', 'employment_status'];

    public static function middleware(): array
    {
        return [new Middleware('school.selected', only: ['create', 'store'])];
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Staff::class);

        $filters = $request->only(self::FILTERS);
        $staff = Staff::query()->filter($filters)
            ->with('school:id,school_code')
            ->orderBy('last_name')->orderBy('first_name')
            ->paginate(25)->withQueryString();

        return view('staff.index', ['staff' => $staff, 'filters' => $filters, 'departments' => self::departments()]);
    }

    public function create(): View
    {
        $this->authorize('create', Staff::class);

        return view('staff.create', ['member' => new Staff(['employment_status' => 'active']), 'departments' => self::departments()]);
    }

    public function store(Request $request, ImageService $images): RedirectResponse
    {
        $this->authorize('create', Staff::class);

        $schoolId = $this->tenant()->schoolIdForWrite();
        $data = $this->validated($request, $schoolId);
        $member = Staff::create(collect($data)->except('photo')->all());
        if ($request->hasFile('photo')) {
            $member->forceFill(['photo_path' => $images->store($request->file('photo'), "staff/{$schoolId}", 600)])->save();
        }

        $this->audit('staff.created', $member, "Created staff {$member->full_name} ({$member->employee_number})");

        return redirect()->route('staff.show', $member)->with('success', 'Staff member created.');
    }

    public function show(Staff $staff): View
    {
        $this->authorize('view', $staff);

        $staff->load('school');
        $idCards = $staff->idCards()->with('template:id,name')->latest('id')->get();

        return view('staff.show', ['member' => $staff, 'idCards' => $idCards]);
    }

    public function edit(Staff $staff): View
    {
        $this->authorize('update', $staff);

        return view('staff.edit', ['member' => $staff, 'departments' => self::departments()]);
    }

    public function update(Request $request, Staff $staff, ImageService $images): RedirectResponse
    {
        $this->authorize('update', $staff);

        $data = $this->validated($request, $staff->school_id, $staff->id);
        $staff->fill(collect($data)->except('photo')->all());
        $changes = array_keys($staff->getDirty());
        if ($request->hasFile('photo')) {
            $old = $staff->photo_path;
            $staff->photo_path = $images->store($request->file('photo'), "staff/{$staff->school_id}", 600);
            $images->delete($old);
            $changes[] = 'photo';
        } elseif ($request->boolean('remove_photo')) {
            $images->delete($staff->photo_path);
            $staff->photo_path = null;
        }
        $staff->save();

        $this->audit('staff.updated', $staff, "Updated staff {$staff->full_name}", ['changed' => $changes]);

        return redirect()->route('staff.show', $staff)->with('success', 'Staff member updated.');
    }

    public function destroy(Staff $staff): RedirectResponse
    {
        $this->authorize('delete', $staff);

        $staff->delete();
        $this->audit('staff.deleted', $staff, "Deleted staff {$staff->full_name}");

        return redirect()->route('staff.index')->with('success', 'Staff member deleted.');
    }

    public function export(Request $request, TableExporter $exporter): Response
    {
        $this->authorize('viewAny', Staff::class);

        $format = in_array($request->query('format'), TableExporter::FORMATS, true) ? $request->query('format') : 'csv';
        $query = Staff::query()->filter($request->only(self::FILTERS))->with('school:id,school_code')->orderBy('last_name');
        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $m) {
                yield [$m->school->school_code, $m->employee_number, $m->full_name, ucfirst($m->gender), $m->date_of_birth?->format('Y-m-d'),
                    $m->job_title, $m->department, $m->phone, $m->email, $m->employment_status];
            }
        })();

        $this->audit('staff.exported', null, "Exported staff ({$format})");

        return $exporter->download($format, 'staff-'.now()->format('Ymd-His'), 'Staff', [
            'School', 'Employee Number', 'Name', 'Gender', 'Date of Birth', 'Job Title', 'Department', 'Phone', 'Email', 'Status',
        ], $rows);
    }

    private static function departments()
    {
        return Staff::query()->whereNotNull('department')->select('department')->distinct()->orderBy('department')->pluck('department');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $schoolId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'employee_number' => ['required', 'string', 'max:40', Rule::unique('staff')->where('school_id', $schoolId)->ignore($ignoreId)],
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['required', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'job_title' => ['nullable', 'string', 'max:80'],
            'department' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'employment_status' => ['required', Rule::in(Staff::STATUSES)],
            'photo' => ['nullable', ...ImageService::UPLOAD_RULES],
        ]);
    }
}
