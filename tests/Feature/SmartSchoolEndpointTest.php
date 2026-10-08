<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SmartSchoolEndpointTest extends TestCase
{
    public function test_endpoint_requires_configured_token(): void
    {
        config(['services.smart_school.endpoint_token' => null]);

        $this->getJson('/api/id-sync/people')
            ->assertStatus(503)
            ->assertJsonPath('message', 'ID sync endpoint is not configured.');
    }

    public function test_endpoint_requires_valid_token(): void
    {
        config(['services.smart_school.endpoint_token' => 'secret']);

        $this->getJson('/api/id-sync/people')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_endpoint_returns_only_id_sync_people_fields_from_database(): void
    {
        $this->configureSmartSchoolDatabase();

        $response = $this->withHeader('X-ID-Sync-Token', 'secret')
            ->getJson('/api/id-sync/people');

        $response->assertOk()
            ->assertJsonPath('source', 'smart_school_database')
            ->assertJsonPath('session_id', 1)
            ->assertJsonPath('students.0.admission_number', 'ADM-001')
            ->assertJsonPath('students.0.first_name', 'Asha')
            ->assertJsonPath('students.0.class_name', 'Form II')
            ->assertJsonPath('students.0.stream', 'A')
            ->assertJsonPath('students.0.academic_year', '2026')
            ->assertJsonPath('staff.0.employee_number', 'EMP-01')
            ->assertJsonPath('staff.0.job_title', 'Teacher')
            ->assertJsonPath('staff.0.department', 'Science');
    }

    private function configureSmartSchoolDatabase(): void
    {
        $path = database_path('smart-school-endpoint-test.sqlite');
        file_put_contents($path, '');

        config([
            'services.smart_school.endpoint_token' => 'secret',
            'services.smart_school.db_connection' => 'sqlite',
            'services.smart_school.db_database' => $path,
            'database.connections.smart_school_endpoint' => array_merge(
                config('database.connections.sqlite'),
                ['database' => $path],
            ),
        ]);
        DB::purge('smart_school_endpoint');

        $schema = Schema::connection('smart_school_endpoint');
        $schema->create('sch_settings', function ($table): void {
            $table->id();
            $table->unsignedInteger('session_id');
        });
        $schema->create('sessions', function ($table): void {
            $table->id();
            $table->string('session');
        });
        $schema->create('classes', function ($table): void {
            $table->id();
            $table->string('class');
        });
        $schema->create('sections', function ($table): void {
            $table->id();
            $table->string('section');
        });
        $schema->create('students', function ($table): void {
            $table->id();
            $table->string('admission_no');
            $table->string('firstname');
            $table->string('middlename')->nullable();
            $table->string('lastname');
            $table->string('gender');
            $table->date('dob')->nullable();
            $table->string('mobileno')->nullable();
            $table->string('guardian_name')->nullable();
            $table->string('guardian_phone')->nullable();
            $table->string('current_address')->nullable();
            $table->string('is_active');
        });
        $schema->create('student_session', function ($table): void {
            $table->id();
            $table->unsignedInteger('student_id');
            $table->unsignedInteger('class_id');
            $table->unsignedInteger('section_id');
            $table->unsignedInteger('session_id');
        });
        $schema->create('staff_designation', function ($table): void {
            $table->id();
            $table->string('designation');
        });
        $schema->create('department', function ($table): void {
            $table->id();
            $table->string('department_name');
        });
        $schema->create('staff', function ($table): void {
            $table->id();
            $table->string('employee_id');
            $table->string('name');
            $table->string('surname');
            $table->string('gender')->nullable();
            $table->date('dob')->nullable();
            $table->unsignedInteger('designation')->nullable();
            $table->unsignedInteger('department')->nullable();
            $table->string('contact_no')->nullable();
            $table->string('email')->nullable();
            $table->boolean('is_active');
        });

        $db = DB::connection('smart_school_endpoint');
        $db->table('sch_settings')->insert(['id' => 1, 'session_id' => 1]);
        $db->table('sessions')->insert(['id' => 1, 'session' => '2026']);
        $db->table('classes')->insert(['id' => 1, 'class' => 'Form II']);
        $db->table('sections')->insert(['id' => 1, 'section' => 'A']);
        $db->table('students')->insert([
            'id' => 1,
            'admission_no' => 'ADM-001',
            'firstname' => 'Asha',
            'middlename' => 'Juma',
            'lastname' => 'Selemani',
            'gender' => 'Female',
            'dob' => '2011-04-05',
            'mobileno' => '0712345678',
            'guardian_name' => 'Juma Selemani',
            'guardian_phone' => '0787654321',
            'current_address' => 'Dar es Salaam',
            'is_active' => 'yes',
        ]);
        $db->table('student_session')->insert([
            'student_id' => 1,
            'class_id' => 1,
            'section_id' => 1,
            'session_id' => 1,
        ]);
        $db->table('staff_designation')->insert(['id' => 1, 'designation' => 'Teacher']);
        $db->table('department')->insert(['id' => 1, 'department_name' => 'Science']);
        $db->table('staff')->insert([
            'employee_id' => 'EMP-01',
            'name' => 'Daudi',
            'surname' => 'Mushi',
            'gender' => 'Male',
            'dob' => '1985-01-01',
            'designation' => 1,
            'department' => 1,
            'contact_no' => '0711111111',
            'email' => 'daudi@example.test',
            'is_active' => 1,
        ]);

        $this->beforeApplicationDestroyed(function () use ($path): void {
            DB::purge('smart_school_endpoint');
            if (is_file($path)) {
                unlink($path);
            }
        });
    }
}
