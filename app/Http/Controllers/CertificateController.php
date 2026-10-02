<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\PrintJob;
use App\Models\Student;
use App\Services\Documents\DocumentData;
use App\Services\Documents\NumberGenerator;
use App\Services\Documents\PageComposer;
use App\Services\Printing\PrintJobService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Issued certificates and the generate wizard:
 * select template -> enter details -> select students -> preview -> generate (print job) -> print.
 */
class CertificateController extends Controller implements HasMiddleware
{
    public const MAX_PER_JOB = 1000;

    public static function middleware(): array
    {
        return [new Middleware('school.selected', only: ['create', 'preview', 'store', 'reprint'])];
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Certificate::class);

        $certificates = Certificate::query()
            ->with(['student:id,admission_number,class_name,stream', 'template:id,name', 'academicYear:id,name', 'school:id,school_code'])
            ->when($request->query('search'), fn (Builder $q, $term) => $q->where(fn (Builder $q) => $q
                ->where('certificate_number', 'like', "%{$term}%")
                ->orWhere('recipient_name', 'like', "%{$term}%")
                ->orWhere('title', 'like', "%{$term}%")
                ->orWhere('program', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('academic_year_id'), fn ($q, $y) => $q->where('academic_year_id', $y))
            ->when($request->query('template_id'), fn ($q, $t) => $q->where('template_id', $t))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('certificates.index', [
            'certificates' => $certificates,
            'academicYears' => AcademicYear::orderByDesc('name')->get(['id', 'name']),
            'templates' => CertificateTemplate::orderBy('name')->get(['id', 'name', 'school_id']),
        ]);
    }

    public function show(Certificate $certificate): View
    {
        $this->authorize('view', $certificate);

        $certificate->load(['student', 'template', 'academicYear', 'school', 'issuer:id,name']);

        return view('certificates.show', compact('certificate'));
    }

    public function create(Request $request): View
    {
        $this->authorize('create', Certificate::class);

        $templates = CertificateTemplate::active()->orderByRaw('school_id is null')->orderBy('name')->get();
        $filters = $request->only(StudentController::FILTERS) + ['status' => 'active'];
        $perPage = in_array((int) $request->query('per_page'), [50, 100, 200, 500], true) ? (int) $request->query('per_page') : 100;

        $students = Student::query()->filter($filters)->with('academicYear:id,name')
            ->orderBy('class_name')->orderBy('stream')->orderBy('last_name')->orderBy('first_name')
            ->paginate($perPage)->withQueryString();

        return view('certificates.generate', [
            'templates' => $templates,
            'students' => $students,
            'filters' => $filters,
            'selectedTemplate' => $request->query('template_id'),
        ] + StudentController::filterOptions());
    }

    public function preview(Request $request, PageComposer $composer): Response
    {
        $this->authorize('create', Certificate::class);

        [$template, $students, $data] = $this->resolveSelection($request);
        $school = $this->tenant()->school();
        $year = isset($data['academic_year_id']) ? AcademicYear::find($data['academic_year_id']) : null;

        $documents = $students->take(4)->values()->map(function ($student, $i) use ($school, $data, $year) {
            $doc = DocumentData::sample('certificate', 'student', $school, $student);
            $doc->text = array_merge($doc->text, [
                'certificate_title' => $data['title'],
                'program' => (string) ($data['program'] ?? ''),
                'description' => (string) ($data['description'] ?? ''),
                'issue_date' => format_date($data['issued_on']),
                'academic_year' => $year?->name ?? $doc->text['academic_year'],
                'certificate_number' => NumberGenerator::preview($school->certificate_number_format, $school->school_code, $year?->name ?? substr($data['issued_on'], 0, 4), $i + 1),
            ]);

            return $doc;
        })->all();

        $composed = $composer->compose($template, $documents, [], 'web');

        return response($composer->html($composed, "Preview – {$template->name} ({$students->count()} selected, showing ".count($documents).')', 'web'));
    }

    public function store(Request $request, PrintJobService $printJobs): RedirectResponse
    {
        $this->authorize('create', Certificate::class);

        [$template, $students, $data] = $this->resolveSelection($request);

        $job = $printJobs->create($this->tenant()->school(), $request->user(), PrintJob::TYPE_CERTIFICATE, $template, $students, [
            'title' => $data['title'],
            'program' => $data['program'] ?? null,
            'description' => $data['description'] ?? null,
            'issued_on' => $data['issued_on'],
            'academic_year_id' => $data['academic_year_id'] ?? null,
        ], $request->ip());

        return redirect()->route('print.show', $job)->with('success', "Print job {$job->job_number} created for {$job->total_items} certificate(s).");
    }

    public function reprint(Request $request, PrintJobService $printJobs): RedirectResponse
    {
        $this->authorize('print.execute');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PER_JOB],
            'ids.*' => ['integer'],
            'template_id' => ['nullable', 'integer'],
        ]);

        $certificates = Certificate::with('student')->whereIn('id', $data['ids'])->where('status', 'valid')->get();
        if ($certificates->isEmpty()) {
            return back()->with('error', 'None of the selected certificates are valid.');
        }

        $template = ($data['template_id'] ?? null)
            ? CertificateTemplate::findOrFail($data['template_id'])
            : CertificateTemplate::find($certificates->first()->template_id);
        abort_unless($template, 422, 'Choose a template for the reprint.');
        $this->authorize('use', $template);

        $job = $printJobs->createReprint($this->tenant()->school(), $request->user(), PrintJob::TYPE_CERTIFICATE, $template, $certificates, [], $request->ip());

        return redirect()->route('print.show', $job)->with('success', "Reprint job {$job->job_number} created.");
    }

    public function revoke(Request $request, Certificate $certificate): RedirectResponse
    {
        $this->authorize('revoke', $certificate);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $certificate->forceFill(['status' => 'revoked', 'revoked_reason' => $data['reason']])->save();
        $this->audit('certificate.revoked', $certificate, "Revoked certificate {$certificate->certificate_number}: {$data['reason']}");

        return back()->with('success', "Certificate {$certificate->certificate_number} revoked. Its QR code now verifies as invalid.");
    }

    /** @return array{0: CertificateTemplate, 1: Collection<int, Student>, 2: array<string, mixed>} */
    private function resolveSelection(Request $request): array
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'issued_on' => ['required', 'date'],
            'academic_year_id' => ['nullable', 'integer'],
            'select_all' => ['nullable', 'boolean'],
            'ids' => ['required_without:select_all', 'array', 'max:'.self::MAX_PER_JOB],
            'ids.*' => ['integer'],
            'filters' => ['nullable', 'array'],
        ], ['ids.required_without' => 'Select at least one student.']);

        $template = CertificateTemplate::findOrFail($data['template_id']);
        $this->authorize('use', $template);

        if (! empty($data['academic_year_id']) && ! AcademicYear::whereKey($data['academic_year_id'])->exists()) {
            abort(422, 'Unknown academic year.');
        }

        $query = Student::query()->with('academicYear:id,name')->orderBy('class_name')->orderBy('stream')->orderBy('last_name')->orderBy('first_name');
        if ($request->boolean('select_all')) {
            $query->filter(array_intersect_key((array) ($data['filters'] ?? []), array_flip(StudentController::FILTERS)));
        } else {
            $query->whereIn('id', $data['ids']);
        }
        $students = $query->limit(self::MAX_PER_JOB + 1)->get();

        if ($students->isEmpty()) {
            back()->withInput()->withErrors(['ids' => 'No matching students were found.'])->throwResponse();
        }
        if ($students->count() > self::MAX_PER_JOB) {
            back()->withInput()->withErrors(['ids' => 'A single job is limited to '.self::MAX_PER_JOB.' certificates. Narrow the filter.'])->throwResponse();
        }

        return [$template, $students, $data];
    }
}
