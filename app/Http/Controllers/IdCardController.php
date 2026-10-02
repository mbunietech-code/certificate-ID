<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\PrintJob;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Documents\DocumentData;
use App\Services\Documents\IdCardIssuer;
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
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Issued ID cards and the generate wizard:
 * select template -> filter & select people -> preview -> generate (print job) -> print.
 */
class IdCardController extends Controller implements HasMiddleware
{
    public const MAX_PER_JOB = 2000;

    public static function middleware(): array
    {
        return [new Middleware('school.selected', only: ['create', 'preview', 'store', 'reprint'])];
    }

    public function index(Request $request): View
    {
        $this->authorize('viewAny', IdCard::class);

        $cards = IdCard::query()
            ->with(['holder', 'template:id,name', 'academicYear:id,name', 'school:id,school_code'])
            ->when($request->query('search'), fn (Builder $q, $term) => $q->where(fn (Builder $q) => $q
                ->where('card_number', 'like', "%{$term}%")
                ->orWhereHasMorph('holder', [Student::class, Staff::class], fn (Builder $h, $type) => $h
                    ->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere($type === Staff::class ? 'employee_number' : 'admission_number', 'like', "%{$term}%"))))
            ->when($request->query('holder_type'), fn ($q, $t) => $q->where('holder_type', $t))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('academic_year_id'), fn ($q, $y) => $q->where('academic_year_id', $y))
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('id-cards.index', [
            'cards' => $cards,
            'academicYears' => AcademicYear::orderByDesc('name')->get(['id', 'name']),
            'templates' => IdCardTemplate::active()->orderBy('name')->get(['id', 'name', 'type', 'school_id']),
        ]);
    }

    public function show(IdCard $idCard): View
    {
        $this->authorize('view', $idCard);

        $idCard->load(['holder', 'template', 'academicYear', 'school', 'issuer:id,name']);

        return view('id-cards.show', ['card' => $idCard]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', IdCard::class);

        $holderType = $request->query('holder') === 'staff' ? 'staff' : 'student';
        $templates = IdCardTemplate::active()
            ->ofType($holderType === 'staff' ? IdCardTemplate::TYPE_STAFF : IdCardTemplate::TYPE_STUDENT)
            ->orderByRaw('school_id is null')->orderBy('name')->get();

        $filters = $request->only($holderType === 'staff' ? StaffController::FILTERS : StudentController::FILTERS);
        $filters += $holderType === 'student' ? ['status' => 'active'] : ['employment_status' => 'active'];
        $perPage = in_array((int) $request->query('per_page'), [50, 100, 200, 500], true) ? (int) $request->query('per_page') : 100;

        $people = $this->holderQuery($holderType, $filters)
            ->with($holderType === 'student' ? ['academicYear:id,name'] : [])
            ->paginate($perPage)->withQueryString();

        $currentCards = IdCard::where('holder_type', $holderType)->whereIn('holder_id', $people->pluck('id'))
            ->where('status', 'active')->pluck('card_number', 'holder_id');

        return view('id-cards.generate', [
            'holderType' => $holderType,
            'templates' => $templates,
            'people' => $people,
            'filters' => $filters,
            'currentCards' => $currentCards,
            'totalMatching' => $people->total(),
            'selectedTemplate' => $request->query('template_id'),
        ] + ($holderType === 'student' ? StudentController::filterOptions() : [
            'departments' => Staff::query()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'academicYears' => AcademicYear::orderByDesc('name')->get(['id', 'name', 'school_id', 'is_current']),
        ]));
    }

    /** Render up to 6 of the selected people with the chosen template, without issuing anything. */
    public function preview(Request $request, PageComposer $composer): Response
    {
        $this->authorize('create', IdCard::class);

        [$template, $holders, $data] = $this->resolveSelection($request);
        $school = $this->tenant()->school();
        $isStaff = $template->holderType() === 'staff';
        $format = $isStaff ? $school->staff_id_format : $school->student_id_format;
        $year = isset($data['academic_year_id']) ? AcademicYear::find($data['academic_year_id']) : null;

        $documents = $holders->take(6)->values()->map(function ($holder, $i) use ($template, $school, $format, $year) {
            $doc = DocumentData::sample('id_card', $template->holderType(), $school, $holder);
            $doc->text['card_number'] = NumberGenerator::preview($format, $school->school_code, $year?->name, $i + 1);
            $doc->text['academic_year'] = $year?->name ?? $doc->text['academic_year'];
            $doc->text['issue_date'] = format_date(now());
            $doc->text['expiry_date'] = format_date($year?->end_date ?? now()->addMonths((int) setting('id_card_validity_months', 12)));

            return $doc;
        })->all();

        $composed = $composer->compose($template, $documents, $this->printOptions($data), 'web');

        return response($composer->html($composed, "Preview – {$template->name} ({$holders->count()} selected, showing ".count($documents).')', 'web'));
    }

    public function store(Request $request, PrintJobService $printJobs): RedirectResponse
    {
        $this->authorize('create', IdCard::class);

        [$template, $holders, $data] = $this->resolveSelection($request);

        $job = $printJobs->create(
            $this->tenant()->school(),
            $request->user(),
            $template->holderType() === 'staff' ? PrintJob::TYPE_STAFF_ID : PrintJob::TYPE_STUDENT_ID,
            $template,
            $holders,
            $this->printOptions($data) + [
                'mode' => $data['mode'],
                'academic_year_id' => $data['academic_year_id'] ?? null,
            ],
            $request->ip(),
        );

        return redirect()->route('print.show', $job)->with('success', "Print job {$job->job_number} created for {$job->total_items} ID card(s).");
    }

    /** Reprint already-issued cards (from the issued cards list or a profile). */
    public function reprint(Request $request, PrintJobService $printJobs): RedirectResponse
    {
        $this->authorize('print.execute');

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_PER_JOB],
            'ids.*' => ['integer'],
            'template_id' => ['nullable', 'integer'],
        ] + $this->optionRules());

        $cards = IdCard::with('holder')->whereIn('id', $data['ids'])->where('status', 'active')->get();
        if ($cards->isEmpty()) {
            return back()->with('error', 'None of the selected cards are active.');
        }

        $groups = $cards->groupBy('holder_type');
        if ($groups->count() > 1) {
            return back()->with('error', 'Select either student cards or staff cards, not both, in one reprint.');
        }

        $template = $data['template_id'] ?? null
            ? IdCardTemplate::findOrFail($data['template_id'])
            : IdCardTemplate::find($cards->first()->template_id);
        abort_unless($template, 422, 'Choose a template for the reprint.');
        $this->authorize('use', $template);
        abort_unless($template->holderType() === $cards->first()->holder_type, 422, 'The template type does not match the selected cards.');

        $job = $printJobs->createReprint($this->tenant()->school(), $request->user(),
            $template->holderType() === 'staff' ? PrintJob::TYPE_STAFF_ID : PrintJob::TYPE_STUDENT_ID,
            $template, $cards, $this->printOptions($data), $request->ip());

        return redirect()->route('print.show', $job)->with('success', "Reprint job {$job->job_number} created.");
    }

    public function revoke(Request $request, IdCard $idCard): RedirectResponse
    {
        $this->authorize('revoke', $idCard);

        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $idCard->forceFill(['status' => 'revoked', 'revoked_reason' => $data['reason']])->save();
        $this->audit('id_card.revoked', $idCard, "Revoked ID {$idCard->card_number}: {$data['reason']}");

        return back()->with('success', "Card {$idCard->card_number} revoked. Its QR code now verifies as invalid.");
    }

    /**
     * Validate the wizard form and resolve the template and people through
     * tenant-scoped queries (ids from other schools simply don't match).
     *
     * @return array{0: IdCardTemplate, 1: Collection<int, Student|Staff>, 2: array<string, mixed>}
     */
    private function resolveSelection(Request $request): array
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer'],
            'holder_type' => ['required', Rule::in(['student', 'staff'])],
            'select_all' => ['nullable', 'boolean'],
            'ids' => ['required_without:select_all', 'array', 'max:'.self::MAX_PER_JOB],
            'ids.*' => ['integer'],
            'filters' => ['nullable', 'array'],
            'mode' => ['required', Rule::in([IdCardIssuer::MODE_REUSE, IdCardIssuer::MODE_NEW])],
            'academic_year_id' => ['nullable', 'integer'],
        ] + $this->optionRules(), ['ids.required_without' => 'Select at least one person.']);

        $template = IdCardTemplate::findOrFail($data['template_id']);
        $this->authorize('use', $template);
        abort_unless($template->holderType() === $data['holder_type'], 422, 'The template type does not match the selection.');

        if (! empty($data['academic_year_id']) && ! AcademicYear::whereKey($data['academic_year_id'])->exists()) {
            abort(422, 'Unknown academic year.');
        }

        $query = $this->holderQuery($data['holder_type'], $request->boolean('select_all') ? (array) ($data['filters'] ?? []) : []);
        if (! $request->boolean('select_all')) {
            $query->whereIn('id', $data['ids']);
        }
        $holders = $query->limit(self::MAX_PER_JOB + 1)->get();

        if ($holders->isEmpty()) {
            back()->withErrors(['ids' => 'No matching people were found.'])->throwResponse();
        }
        if ($holders->count() > self::MAX_PER_JOB) {
            back()->withErrors(['ids' => 'A single job is limited to '.self::MAX_PER_JOB.' cards. Narrow the filter.'])->throwResponse();
        }

        return [$template, $holders, $data];
    }

    /** @param  array<string, mixed>  $filters */
    private function holderQuery(string $holderType, array $filters): Builder
    {
        if ($holderType === 'staff') {
            return Staff::query()->filter(array_intersect_key($filters, array_flip(StaffController::FILTERS)))
                ->orderBy('last_name')->orderBy('first_name');
        }

        return Student::query()->filter(array_intersect_key($filters, array_flip(StudentController::FILTERS)))
            ->with('academicYear:id,name')
            ->orderBy('class_name')->orderBy('stream')->orderBy('last_name')->orderBy('first_name');
    }

    /** @return array<string, mixed> */
    private function optionRules(): array
    {
        return [
            'layout' => ['required', Rule::in(['card', 'sheet'])],
            'include_back' => ['nullable', 'boolean'],
            'crop_marks' => ['nullable', 'boolean'],
            'paper' => ['nullable', Rule::in(array_keys(PageComposer::SHEET_SIZES))],
            'margin' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'gap' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ];
    }

    /** @param  array<string, mixed>  $data */
    private function printOptions(array $data): array
    {
        return [
            'layout' => $data['layout'] ?? 'card',
            'include_back' => (bool) ($data['include_back'] ?? false),
            'crop_marks' => (bool) ($data['crop_marks'] ?? false),
            'paper' => $data['paper'] ?? 'A4',
            'margin' => (float) ($data['margin'] ?? 10),
            'gap' => (float) ($data['gap'] ?? 4),
        ];
    }
}
