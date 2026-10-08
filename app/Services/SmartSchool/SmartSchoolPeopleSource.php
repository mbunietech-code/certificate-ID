<?php

namespace App\Services\SmartSchool;

use App\Models\School;
use App\Services\StudentImportService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class SmartSchoolPeopleSource
{
    /**
     * @return array{source: string, students: array<int, array<string, mixed>>, staff: array<int, array<string, mixed>>}
     */
    public function fetch(School $school): array
    {
        return match ($school->smart_school_source) {
            'api' => $this->fetchFromEndpoint($school),
            'database' => $this->fetchFromDatabase($school),
            default => throw ValidationException::withMessages([
                'smart_school' => 'Smart School source is not configured for this school.',
            ]),
        };
    }

    /**
     * @return array{source: string, students: array<int, array<string, mixed>>, staff: array<int, array<string, mixed>>}
     */
    private function fetchFromEndpoint(School $school): array
    {
        $endpoint = trim((string) $school->smart_school_endpoint_url);
        if ($endpoint === '') {
            throw ValidationException::withMessages(['smart_school_endpoint_url' => 'Set the Smart School endpoint URL first.']);
        }

        try {
            $request = Http::timeout(30)->acceptJson();
            if (filled($school->smart_school_api_token)) {
                $request = $request->withToken($school->smart_school_api_token)->withHeaders([
                    'X-ID-Sync-Token' => $school->smart_school_api_token,
                ]);
            }

            $response = $request->get($endpoint);
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'smart_school' => 'Could not connect to the Smart School endpoint: '.$exception->getMessage(),
            ]);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages([
                'smart_school' => "Smart School endpoint returned HTTP {$response->status()}.",
            ]);
        }

        $payload = $response->json();
        if (! is_array($payload)) {
            throw ValidationException::withMessages(['smart_school' => 'Smart School endpoint did not return valid JSON.']);
        }

        $container = is_array($payload['data'] ?? null) ? $payload['data'] : $payload;
        $students = array_is_list($container) ? $container : $this->records($container['students'] ?? []);
        $staff = array_is_list($container) ? [] : $this->records($container['staff'] ?? []);

        return [
            'source' => $endpoint,
            'students' => $this->normalizeStudents($students),
            'staff' => $this->normalizeStaff($staff),
        ];
    }

    /**
     * @return array{source: string, students: array<int, array<string, mixed>>, staff: array<int, array<string, mixed>>}
     */
    private function fetchFromDatabase(School $school): array
    {
        $database = $school->smart_school_database ?: config('services.smart_school.db_database');
        if (! $database) {
            throw ValidationException::withMessages(['smart_school_database' => 'Set a Smart School database name first.']);
        }

        $connection = $this->connection($school, $database);
        $sessionId = $this->currentSessionId($connection);

        try {
            $students = $this->studentQuery($connection, $sessionId)->limit(StudentImportService::MAX_ROWS)->get()->map(fn ($row) => (array) $row)->all();
            $staff = $this->staffQuery($connection)->limit(StudentImportService::MAX_ROWS)->get()->map(fn ($row) => (array) $row)->all();
        } catch (Throwable $exception) {
            throw ValidationException::withMessages([
                'smart_school' => 'Could not read Smart School database: '.$exception->getMessage(),
            ]);
        }

        return [
            'source' => "database:{$database}",
            'students' => $this->normalizeStudents($students),
            'staff' => $this->normalizeStaff($staff),
        ];
    }

    private function connection(School $school, string $database): ConnectionInterface
    {
        $name = 'smart_school_sync_'.$school->id;
        $base = config('database.connections.mysql');
        config([
            "database.connections.{$name}" => array_merge($base, [
                'host' => config('services.smart_school.db_host'),
                'port' => config('services.smart_school.db_port'),
                'database' => $database,
                'username' => config('services.smart_school.db_username'),
                'password' => config('services.smart_school.db_password'),
                'unix_socket' => config('services.smart_school.db_socket'),
            ]),
        ]);

        DB::purge($name);

        return DB::connection($name);
    }

    private function currentSessionId(ConnectionInterface $connection): ?int
    {
        try {
            $value = $connection->table('sch_settings')->value('session_id');

            return $value ? (int) $value : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function studentQuery(ConnectionInterface $connection, ?int $sessionId): Builder
    {
        return $connection->table('students')
            ->join('student_session', 'student_session.student_id', '=', 'students.id')
            ->join('classes', 'classes.id', '=', 'student_session.class_id')
            ->join('sections', 'sections.id', '=', 'student_session.section_id')
            ->leftJoin('sessions', 'sessions.id', '=', 'student_session.session_id')
            ->when($sessionId, fn (Builder $query) => $query->where('student_session.session_id', $sessionId))
            ->where('students.is_active', 'yes')
            ->orderBy('students.admission_no')
            ->select([
                'students.admission_no as admission_number',
                'students.firstname as first_name',
                'students.middlename as middle_name',
                'students.lastname as last_name',
                'students.gender',
                'students.dob as date_of_birth',
                'students.mobileno as student_phone',
                'students.guardian_name as parent_name',
                'students.guardian_phone as parent_phone',
                'students.current_address as address',
                'classes.class as class_name',
                'sections.section as stream',
                'sessions.session as academic_year',
            ]);
    }

    private function staffQuery(ConnectionInterface $connection): Builder
    {
        return $connection->table('staff')
            ->leftJoin('staff_designation', 'staff_designation.id', '=', 'staff.designation')
            ->leftJoin('department', 'department.id', '=', 'staff.department')
            ->where('staff.is_active', 1)
            ->orderBy('staff.employee_id')
            ->select([
                'staff.employee_id as employee_number',
                'staff.name as first_name',
                'staff.surname as last_name',
                'staff.gender',
                'staff.dob as date_of_birth',
                'staff.contact_no as phone',
                'staff.email',
                'staff_designation.designation as job_title',
                'department.department_name as department',
            ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStudents(array $rows): array
    {
        return array_values(array_map(fn (array $row) => [
            'admission_number' => $this->first($row, ['admission_number', 'admission_no', 'adm_no', 'registration_number']),
            'first_name' => $this->first($row, ['first_name', 'firstname', 'given_name']),
            'middle_name' => $this->first($row, ['middle_name', 'middlename', 'other_name']),
            'last_name' => $this->first($row, ['last_name', 'lastname', 'surname', 'family_name']),
            'gender' => $this->first($row, ['gender', 'sex']),
            'date_of_birth' => $this->first($row, ['date_of_birth', 'dob', 'birth_date']),
            'level' => $this->first($row, ['level', 'education_level']),
            'class_name' => $this->first($row, ['class_name', 'class', 'form', 'grade']),
            'stream' => $this->first($row, ['stream', 'section', 'section_name']),
            'combination' => $this->first($row, ['combination', 'subject_combination']),
            'entry_year' => $this->first($row, ['entry_year', 'admission_year']),
            'completion_year' => $this->first($row, ['completion_year', 'graduation_year']),
            'academic_year' => $this->first($row, ['academic_year', 'session', 'year']),
            'nationality' => $this->first($row, ['nationality']),
            'parent_name' => $this->first($row, ['parent_name', 'guardian_name', 'father_name']),
            'parent_phone' => $this->first($row, ['parent_phone', 'guardian_phone', 'father_phone']),
            'student_phone' => $this->first($row, ['student_phone', 'phone', 'mobileno', 'mobile_no']),
            'address' => $this->first($row, ['address', 'current_address', 'permanent_address']),
        ], $this->validRows($rows)));
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function normalizeStaff(array $rows): array
    {
        return array_values(array_map(fn (array $row) => [
            'employee_number' => $this->first($row, ['employee_number', 'employee_id', 'staff_id']),
            'first_name' => $this->first($row, ['first_name', 'firstname', 'name']),
            'middle_name' => $this->first($row, ['middle_name', 'middlename']),
            'last_name' => $this->first($row, ['last_name', 'lastname', 'surname']),
            'gender' => $this->first($row, ['gender', 'sex']),
            'date_of_birth' => $this->first($row, ['date_of_birth', 'dob']),
            'job_title' => $this->first($row, ['job_title', 'designation', 'role']),
            'department' => $this->first($row, ['department', 'department_name']),
            'phone' => $this->first($row, ['phone', 'contact_no', 'mobile_no']),
            'email' => $this->first($row, ['email']),
        ], $this->validRows($rows)));
    }

    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function validRows(array $rows): array
    {
        return array_values(array_filter($rows, fn ($row) => is_array($row)));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function records(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_is_list($value) ? $value : [$value];
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<int, string>  $keys
     */
    private function first(array $row, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== null && $row[$key] !== '') {
                return $row[$key];
            }
        }

        return null;
    }
}
