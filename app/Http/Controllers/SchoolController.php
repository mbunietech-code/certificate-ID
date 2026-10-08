<?php

namespace App\Http\Controllers;

use App\Models\AcademicYear;
use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\PrintJob;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\Documents\NumberGenerator;
use App\Services\ImageService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SchoolController extends Controller
{
    public const IMAGE_FIELDS = [
        'logo' => 'logo_path',
        'principal_signature' => 'principal_signature_path',
        'school_stamp' => 'school_stamp_path',
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', School::class);

        $schools = School::query()
            ->when($request->query('search'), fn ($q, $term) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$term}%")->orWhere('school_code', 'like', "%{$term}%")->orWhere('region', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->withCount(['students' => fn ($q) => $q->where('status', 'active'), 'staff', 'users'])
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('schools.index', compact('schools'));
    }

    public function create(): View
    {
        $this->authorize('create', School::class);

        return view('schools.create', ['school' => new School([
            'primary_color' => '#1e3a8a',
            'secondary_color' => '#b45309',
            'student_id_format' => setting('default_student_id_format'),
            'staff_id_format' => setting('default_staff_id_format'),
            'certificate_number_format' => setting('default_certificate_number_format'),
        ])]);
    }

    public function store(Request $request, ImageService $images): RedirectResponse
    {
        $this->authorize('create', School::class);

        $data = $request->validate(self::rules() + [
            'school_code' => ['required', 'string', 'max:20', 'alpha_num', 'unique:schools,school_code'],
            'admin_name' => ['nullable', 'required_with:admin_email', 'string', 'max:120'],
            'admin_email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
            'admin_password' => ['nullable', 'required_with:admin_email', Password::min(8)->letters()->numbers()],
        ]);

        $school = DB::transaction(function () use ($data, $request, $images) {
            $school = new School(self::attributes($data));
            $school->school_code = strtoupper($data['school_code']);
            $school->status = 'active';
            $school->save();
            self::storeImages($school, $request, $images);

            $year = (string) now()->year;
            app(TenantContext::class)->runFor($school->id, fn () => AcademicYear::create([
                'name' => $year, 'start_date' => "{$year}-01-01", 'end_date' => "{$year}-12-31", 'is_current' => true,
            ]));

            if (! empty($data['admin_email'])) {
                $admin = new User(['name' => $data['admin_name'], 'email' => $data['admin_email'], 'password' => $data['admin_password'], 'status' => 'active']);
                $admin->school_id = $school->id;
                $admin->role_id = Role::where('slug', Role::SCHOOL_ADMIN)->value('id');
                $admin->save();
                $this->audit('user.created', $admin, "Created school admin {$admin->email}");
            }

            return $school;
        });

        $this->audit('school.created', $school, "Created school {$school->name} ({$school->school_code})");

        return redirect()->route('schools.show', $school)->with('success', 'School created.');
    }

    public function show(School $school): View
    {
        $this->authorize('view', $school);

        $school->loadCount(['students', 'staff', 'users']);
        $admins = User::where('school_id', $school->id)->with('role')->orderBy('name')->get();
        $years = AcademicYear::forSchool($school->id)->orderByDesc('name')->get();
        $counts = [
            'id_cards' => IdCard::forSchool($school->id)->count(),
            // Includes IDs marked "Taken" even when they were printed without a card record (e.g. PNG download).
            'ids_printed' => Student::forSchool($school->id)->idPrinted()->count(),
            'certificates' => Certificate::forSchool($school->id)->count(),
            'print_jobs' => PrintJob::forSchool($school->id)->count(),
        ];

        return view('schools.show', compact('school', 'admins', 'years', 'counts'));
    }

    public function edit(School $school): View
    {
        $this->authorize('update', $school);

        return view('schools.edit', compact('school'));
    }

    public function update(Request $request, School $school, ImageService $images): RedirectResponse
    {
        $this->authorize('update', $school);

        $data = $request->validate(self::rules() + [
            'school_code' => ['required', 'string', 'max:20', 'alpha_num', Rule::unique('schools', 'school_code')->ignore($school->id)],
            'status' => ['required', Rule::in(School::STATUSES)],
        ]);

        $school->fill(self::attributes($data));
        $school->school_code = strtoupper($data['school_code']);
        $school->status = $data['status'];
        $changes = array_keys($school->getDirty());
        $school->save();
        self::storeImages($school, $request, $images);

        $this->audit('school.updated', $school, "Updated school {$school->name}", ['changed' => $changes]);

        return redirect()->route('schools.show', $school)->with('success', 'School updated.');
    }

    public function toggleStatus(School $school): RedirectResponse
    {
        $this->authorize('update', $school);

        $school->status = $school->isActive() ? 'inactive' : 'active';
        $school->save();
        $this->audit('school.status_changed', $school, "School {$school->name} set to {$school->status}");

        return back()->with('success', "{$school->name} is now {$school->status}.");
    }

    /** Validation shared with the school-admin profile page (code/status excluded). */
    public static function rules(): array
    {
        $format = function (string $attribute, mixed $value, Closure $fail) {
            if ($error = NumberGenerator::validateFormat((string) $value)) {
                $fail($error);
            }
        };

        return [
            'name' => ['required', 'string', 'max:255'],
            'short_name' => ['nullable', 'string', 'max:60'],
            'registration_number' => ['nullable', 'string', 'max:60'],
            'address' => ['nullable', 'string', 'max:500'],
            'region' => ['nullable', 'string', 'max:80'],
            'district' => ['nullable', 'string', 'max:80'],
            'ward' => ['nullable', 'string', 'max:80'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'website' => ['nullable', 'string', 'max:255'],
            'principal_name' => ['nullable', 'string', 'max:255'],
            'primary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'student_id_format' => ['required', 'string', 'max:80', $format],
            'staff_id_format' => ['required', 'string', 'max:80', $format],
            'certificate_number_format' => ['required', 'string', 'max:80', $format],
            'smart_school_source' => ['nullable', Rule::in(School::SMART_SCHOOL_SOURCES)],
            'smart_school_endpoint_url' => ['nullable', 'required_if:smart_school_source,api', 'url', 'max:500'],
            'smart_school_api_token' => ['nullable', 'string', 'max:1000'],
            'smart_school_database' => ['nullable', 'string', 'max:100'],
            'logo' => ['nullable', ...ImageService::UPLOAD_RULES],
            'principal_signature' => ['nullable', ...ImageService::UPLOAD_RULES],
            'school_stamp' => ['nullable', ...ImageService::UPLOAD_RULES],
            'remove_images' => ['nullable', 'array'],
            'remove_images.*' => [Rule::in(array_keys(self::IMAGE_FIELDS))],
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function attributes(array $data): array
    {
        $attributes = collect($data)->only([
            'name', 'short_name', 'registration_number', 'address', 'region', 'district', 'ward', 'phone', 'email',
            'website', 'principal_name', 'primary_color', 'secondary_color', 'student_id_format', 'staff_id_format',
            'certificate_number_format', 'smart_school_source', 'smart_school_endpoint_url', 'smart_school_database',
        ])->all();

        if (array_key_exists('smart_school_api_token', $data) && filled($data['smart_school_api_token'])) {
            $attributes['smart_school_api_token'] = $data['smart_school_api_token'];
        }

        if (($attributes['smart_school_source'] ?? null) !== 'api') {
            $attributes['smart_school_endpoint_url'] = null;
        }

        if (($attributes['smart_school_source'] ?? null) !== 'database') {
            $attributes['smart_school_database'] = null;
        }

        return $attributes;
    }

    /** Store/replace/remove the logo, signature and stamp (transparent PNGs). */
    public static function storeImages(School $school, Request $request, ImageService $images): void
    {
        $remove = (array) $request->input('remove_images', []);

        foreach (self::IMAGE_FIELDS as $input => $column) {
            if ($request->hasFile($input)) {
                $old = $school->{$column};
                $school->{$column} = $images->store($request->file($input), "schools/{$school->id}", 1000, true);
                $images->delete($old);
            } elseif (in_array($input, $remove, true)) {
                $images->delete($school->{$column});
                $school->{$column} = null;
            }
        }

        if ($school->isDirty()) {
            $school->save();
        }
    }
}
