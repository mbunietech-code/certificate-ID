<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class SmartSchoolPeopleEndpointController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $auth = $this->authorizeToken($request);
        if ($auth) {
            return $auth;
        }

        $limit = max(1, min((int) $request->integer('limit', 5000), 10000));
        $include = $this->include($request);
        $connection = $this->connection();
        $sessionId = $this->currentSessionId($connection);

        try {
            $students = in_array('students', $include, true)
                ? $this->students($connection, $sessionId, $limit)->get()
                : collect();
            $staff = in_array('staff', $include, true)
                ? $this->staff($connection, $limit)->get()
                : collect();
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Could not read Smart School database.',
                'error' => config('app.debug') ? $exception->getMessage() : null,
            ], 500);
        }

        return response()->json([
            'source' => 'smart_school_database',
            'session_id' => $sessionId,
            'generated_at' => now()->toIso8601String(),
            'students' => $students,
            'staff' => $staff,
        ]);
    }

    private function authorizeToken(Request $request): ?JsonResponse
    {
        $expected = (string) config('services.smart_school.endpoint_token');
        if ($expected === '') {
            return response()->json(['message' => 'ID sync endpoint is not configured.'], 503);
        }

        $provided = (string) $request->header('X-ID-Sync-Token');
        $authorization = (string) $request->header('Authorization');

        if ($provided === '' && preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)) {
            $provided = $matches[1];
        }

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        return null;
    }

    /** @return array<int, string> */
    private function include(Request $request): array
    {
        $include = array_filter(array_map(
            fn (string $value) => trim(strtolower($value)),
            explode(',', (string) $request->query('include', 'students,staff')),
        ));

        $include = array_values(array_intersect($include, ['students', 'staff']));

        return $include ?: ['students', 'staff'];
    }

    private function connection(): ConnectionInterface
    {
        $driver = (string) config('services.smart_school.db_connection', 'mysql');
        $base = config("database.connections.{$driver}");

        if (! is_array($base)) {
            $base = config('database.connections.mysql');
            $driver = 'mysql';
        }

        $overrides = $driver === 'sqlite'
            ? ['database' => config('services.smart_school.db_database')]
            : [
                'host' => config('services.smart_school.db_host'),
                'port' => config('services.smart_school.db_port'),
                'database' => config('services.smart_school.db_database'),
                'username' => config('services.smart_school.db_username'),
                'password' => config('services.smart_school.db_password'),
                'unix_socket' => config('services.smart_school.db_socket'),
            ];

        config(['database.connections.smart_school_endpoint' => array_merge($base, $overrides)]);
        DB::purge('smart_school_endpoint');

        return DB::connection('smart_school_endpoint');
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

    private function students(ConnectionInterface $connection, ?int $sessionId, int $limit): Builder
    {
        return $connection->table('students')
            ->join('student_session', 'student_session.student_id', '=', 'students.id')
            ->join('classes', 'classes.id', '=', 'student_session.class_id')
            ->join('sections', 'sections.id', '=', 'student_session.section_id')
            ->leftJoin('sessions', 'sessions.id', '=', 'student_session.session_id')
            ->when($sessionId, fn (Builder $query) => $query->where('student_session.session_id', $sessionId))
            ->where('students.is_active', 'yes')
            ->orderBy('students.admission_no')
            ->limit($limit)
            ->select([
                'students.admission_no as admission_number',
                'students.firstname as first_name',
                'students.middlename as middle_name',
                'students.lastname as last_name',
                'students.gender',
                'students.dob as date_of_birth',
                'classes.class as class_name',
                'sections.section as stream',
                'sessions.session as academic_year',
                'students.guardian_name as parent_name',
                'students.guardian_phone as parent_phone',
                'students.mobileno as student_phone',
                'students.current_address as address',
            ]);
    }

    private function staff(ConnectionInterface $connection, int $limit): Builder
    {
        return $connection->table('staff')
            ->leftJoin('staff_designation', 'staff_designation.id', '=', 'staff.designation')
            ->leftJoin('department', 'department.id', '=', 'staff.department')
            ->where('staff.is_active', 1)
            ->orderBy('staff.employee_id')
            ->limit($limit)
            ->select([
                'staff.employee_id as employee_number',
                'staff.name as first_name',
                'staff.surname as last_name',
                'staff.gender',
                'staff.dob as date_of_birth',
                'staff_designation.designation as job_title',
                'department.department_name as department',
                'staff.contact_no as phone',
                'staff.email',
            ]);
    }
}
