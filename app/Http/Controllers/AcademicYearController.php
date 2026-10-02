<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicYearController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [new Middleware('school.selected', only: ['create', 'store'])];
    }

    public function index(): View
    {
        $this->authorize('viewAny', AcademicYear::class);

        $years = AcademicYear::with('school:id,school_code')
            ->withCount('students')
            ->orderBy('school_id')->orderByDesc('name')
            ->paginate(30);

        return view('academic-years.index', compact('years'));
    }

    public function create(): View
    {
        $this->authorize('create', AcademicYear::class);

        $year = (string) now()->year;

        return view('academic-years.form', ['year' => new AcademicYear(['name' => $year, 'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31"])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', AcademicYear::class);

        $schoolId = $this->tenant()->schoolIdForWrite();
        $data = $this->validated($request, $schoolId);
        $year = AcademicYear::create(collect($data)->except('is_current')->all());
        if ($request->boolean('is_current') || ! AcademicYear::where('is_current', true)->exists()) {
            $year->markCurrent();
        }

        $this->audit('academic_year.created', $year, "Created academic year {$year->name}");

        return redirect()->route('academic-years.index')->with('success', 'Academic year created.');
    }

    public function edit(AcademicYear $academicYear): View
    {
        $this->authorize('update', $academicYear);

        return view('academic-years.form', ['year' => $academicYear]);
    }

    public function update(Request $request, AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('update', $academicYear);

        $data = $this->validated($request, $academicYear->school_id, $academicYear->id);
        $academicYear->update(collect($data)->except('is_current')->all());
        if ($request->boolean('is_current')) {
            $academicYear->markCurrent();
        }

        $this->audit('academic_year.updated', $academicYear, "Updated academic year {$academicYear->name}");

        return redirect()->route('academic-years.index')->with('success', 'Academic year updated.');
    }

    public function makeCurrent(AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('update', $academicYear);

        $academicYear->markCurrent();
        $this->audit('academic_year.current', $academicYear, "Set {$academicYear->name} as current academic year");

        return back()->with('success', "{$academicYear->name} is now the current academic year.");
    }

    public function destroy(AcademicYear $academicYear): RedirectResponse
    {
        $this->authorize('delete', $academicYear);

        if ($academicYear->students()->exists()) {
            return back()->with('error', 'This academic year has students and cannot be deleted. Set it inactive instead.');
        }

        $academicYear->delete();
        $this->audit('academic_year.deleted', $academicYear, "Deleted academic year {$academicYear->name}");

        return redirect()->route('academic-years.index')->with('success', 'Academic year deleted.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $schoolId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:20', Rule::unique('academic_years')->where('school_id', $schoolId)->ignore($ignoreId)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'is_current' => ['nullable', 'boolean'],
        ]);
    }
}
