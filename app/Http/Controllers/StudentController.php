<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Services\ImageService;
use App\Services\TableExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StudentController extends Controller implements HasMiddleware
{
    public const FILTERS = ['search', 'level', 'class_name', 'stream', 'gender', 'academic_year_id', 'status'];

    public static function middleware(): array
    {
        return [new Middleware('school.selected', only: ['create', 'store'])];
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Student::class);

        $filters = $request->only(self::FILTERS);
        $perPage = in_array((int) $request->query('per_page'), [25, 50, 100], true) ? (int) $request->query('per_page') : 25;

        $students = Student::query()
            ->filter($filters)
            ->with(['academicYear:id,name', 'school:id,school_code'])
            ->orderBy('class_name')->orderBy('stream')->orderBy('last_name')->orderBy('first_name')
            ->paginate($perPage)
            ->withQueryString();

        return view('students.index', ['students' => $students, 'filters' => $filters] + self::filterOptions());
    }

    public function create(): View
    {
        $this->authorize('create', Student::class);

        return view('students.create', [
            'student' => new Student(['status' => 'active', 'nationality' => 'Tanzanian',
                'academic_year_id' => AcademicYear::where('is_current', true)->value('id')]),
        ] + self::filterOptions());
    }

    public function store(Request $request, ImageService $images): RedirectResponse
    {
        $this->authorize('create', Student::class);

        $schoolId = $this->tenant()->schoolIdForWrite();
        $data = $this->validated($request, $schoolId);

        $student = DB::transaction(function () use ($data, $request, $images, $schoolId) {
            $student = Student::create(collect($data)->except('photo')->all());
            if ($request->hasFile('photo')) {
                $student->forceFill(['photo_path' => $images->store($request->file('photo'), "students/{$schoolId}", 600)])->save();
            }

            return $student;
        });

        $this->audit('student.created', $student, "Created student {$student->full_name} ({$student->admission_number})");

        return $request->boolean('add_another')
            ? redirect()->route('students.create')->with('success', "{$student->full_name} added.")
            : redirect()->route('students.show', $student)->with('success', 'Student created.');
    }

    public function show(Student $student): View
    {
        $this->authorize('view', $student);

        $student->load(['academicYear', 'school']);
        $idCards = $student->idCards()->with('template:id,name')->latest('id')->get();
        $certificates = $student->certificates()->latest('id')->get();

        return view('students.show', compact('student', 'idCards', 'certificates'));
    }

    public function edit(Student $student): View
    {
        $this->authorize('update', $student);

        return view('students.edit', ['student' => $student] + self::filterOptions());
    }

    public function update(Request $request, Student $student, ImageService $images): RedirectResponse
    {
        $this->authorize('update', $student);

        $data = $this->validated($request, $student->school_id, $student->id);
        $student->fill(collect($data)->except('photo')->all());
        $changes = array_keys($student->getDirty());

        if ($request->hasFile('photo')) {
            $old = $student->photo_path;
            $student->photo_path = $images->store($request->file('photo'), "students/{$student->school_id}", 600);
            $images->delete($old);
            $changes[] = 'photo';
        } elseif ($request->boolean('remove_photo')) {
            $images->delete($student->photo_path);
            $student->photo_path = null;
            $changes[] = 'photo';
        }
        $student->save();

        $this->audit('student.updated', $student, "Updated student {$student->full_name}", ['changed' => $changes]);

        return redirect()->route('students.show', $student)->with('success', 'Student updated.');
    }

    public function destroy(Student $student): RedirectResponse
    {
        $this->authorize('delete', $student);

        $student->delete();
        $this->audit('student.deleted', $student, "Deleted student {$student->full_name} ({$student->admission_number})");

        return redirect()->route('students.index')->with('success', 'Student deleted. It can be restored from the "Deleted" filter.');
    }

    public function restore(int $id): RedirectResponse
    {
        $student = Student::onlyTrashed()->findOrFail($id);
        $this->authorize('delete', $student);

        $student->restore();
        $this->audit('student.restored', $student, "Restored student {$student->full_name}");

        return redirect()->route('students.show', $student)->with('success', 'Student restored.');
    }

    /** Bulk status change / delete for the selected students of the current page. */
    public function bulk(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'max:500'],
            'ids.*' => ['integer'],
            'action' => ['required', Rule::in(['activate', 'deactivate', 'graduate', 'delete'])],
        ]);

        // Tenant scope drops ids of other schools silently.
        $students = Student::whereIn('id', $data['ids'])->get();
        $ability = $data['action'] === 'delete' ? 'delete' : 'update';
        $students->each(fn ($s) => $this->authorize($ability, $s));

        DB::transaction(function () use ($students, $data) {
            foreach ($students as $student) {
                match ($data['action']) {
                    'delete' => $student->delete(),
                    'activate' => $student->update(['status' => 'active']),
                    'deactivate' => $student->update(['status' => 'inactive']),
                    'graduate' => $student->update(['status' => 'graduated']),
                };
            }
        });

        $this->audit("student.bulk_{$data['action']}", null, "Bulk {$data['action']} of {$students->count()} students", ['ids' => $students->pluck('id')->all()]);

        return back()->with('success', "{$students->count()} student(s) updated.");
    }

    public function export(Request $request, TableExporter $exporter): Response
    {
        $this->authorize('students.export');

        $format = in_array($request->query('format'), TableExporter::FORMATS, true) ? $request->query('format') : 'csv';
        $query = Student::query()->filter($request->only(self::FILTERS))->with(['academicYear:id,name', 'school:id,school_code'])
            ->orderBy('class_name')->orderBy('stream')->orderBy('last_name');

        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $s) {
                yield [$s->school->school_code, $s->admission_number, $s->first_name, $s->middle_name, $s->last_name, ucfirst($s->gender),
                    $s->date_of_birth?->format('Y-m-d'), $s->nationality, $s->level, $s->class_name, $s->stream, $s->combination,
                    $s->entry_year, $s->completion_year, $s->academicYear?->name, $s->parent_name, $s->parent_phone, $s->student_phone, $s->address, $s->status];
            }
        })();

        $this->audit('student.exported', null, "Exported students ({$format})", ['filters' => $request->only(self::FILTERS)]);

        return $exporter->download($format, 'students-'.now()->format('Ymd-His'), 'Students', [
            'School', 'Admission Number', 'First Name', 'Middle Name', 'Last Name', 'Gender', 'Date of Birth', 'Nationality',
            'Level', 'Class', 'Stream', 'Combination', 'Entry Year', 'Completion Year', 'Academic Year', 'Parent Name', 'Parent Phone', 'Student Phone', 'Address', 'Status',
        ], $rows);
    }

    /** @return array<string, mixed> distinct classes/streams and academic years of the visible school(s) */
    public static function filterOptions(): array
    {
        return [
            'levels' => collect(Student::LEVELS)->merge(Student::query()->whereNotNull('level')->distinct()->pluck('level'))->unique()->sort()->values(),
            'classes' => Student::query()->select('class_name')->distinct()->orderBy('class_name')->pluck('class_name'),
            'streams' => Student::query()->whereNotNull('stream')->where('stream', '!=', '')->select('stream')->distinct()->orderBy('stream')->pluck('stream'),
            'academicYears' => AcademicYear::orderByDesc('name')->get(['id', 'name', 'school_id', 'is_current']),
        ];
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, int $schoolId, ?int $ignoreId = null): array
    {
        return $request->validate([
            'admission_number' => ['required', 'string', 'max:40',
                Rule::unique('students')->where('school_id', $schoolId)->ignore($ignoreId)],
            'first_name' => ['required', 'string', 'max:60'],
            'middle_name' => ['nullable', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['required', Rule::in(Student::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:today', 'after:1900-01-01'],
            'nationality' => ['nullable', 'string', 'max:60'],
            'level' => ['nullable', 'string', 'max:20'],
            'class_name' => ['required', 'string', 'max:40'],
            'stream' => ['nullable', 'string', 'max:30'],
            'combination' => ['nullable', 'string', 'max:40'],
            'entry_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'completion_year' => ['nullable', 'integer', 'min:1950', 'max:2100', 'gte:entry_year'],
            'academic_year_id' => ['nullable', 'integer', Rule::exists('academic_years', 'id')->where('school_id', $schoolId)],
            'parent_name' => ['nullable', 'string', 'max:120'],
            'parent_phone' => ['nullable', 'string', 'max:40'],
            'student_phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(Student::STATUSES)],
            'photo' => ['nullable', ...ImageService::UPLOAD_RULES],
        ], [
            'admission_number.unique' => 'This admission number already exists in this school (it may be a deleted student – check the "Deleted" filter).',
        ]);
    }
}
