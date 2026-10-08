<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Student;
use App\Models\StudentImport;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmartSchoolImportTest extends TestCase
{
    public function test_smart_school_api_students_can_be_previewed_and_confirmed(): void
    {
        $school = $this->school('BMK');
        $school->forceFill([
            'smart_school_source' => 'api',
            'smart_school_endpoint_url' => 'https://benjamin.sc.tz/id_sync/people',
            'smart_school_api_token' => 'secret-token',
        ])->save();

        Http::fake([
            'benjamin.sc.tz/*' => Http::response([
                'students' => [
                    [
                        'admission_no' => 'BMK-001',
                        'firstname' => 'Asha',
                        'middlename' => 'Juma',
                        'lastname' => 'Selemani',
                        'gender' => 'Female',
                        'class' => 'Form II',
                        'section' => 'A',
                        'session' => '2026',
                    ],
                ],
                'staff' => [
                    ['employee_id' => 'EMP-01', 'name' => 'Daudi', 'surname' => 'Mushi', 'gender' => 'Male'],
                ],
            ]),
        ]);

        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $response = $this->post(route('students.import.smart-school'), ['duplicate_mode' => 'skip']);

        $import = StudentImport::firstOrFail();
        $response->assertRedirect(route('students.import.show', $import));
        $this->assertSame(1, $import->summary['total']);
        $this->assertSame(1, $import->summary['valid']);
        $this->assertSame('https://benjamin.sc.tz/id_sync/people', $import->summary['source']);
        $this->assertSame(1, $import->summary['staff_available']);
        $this->assertSame(0, Student::count(), 'Nothing is imported before confirmation.');

        $this->post(route('students.import.confirm', $import))->assertRedirect(route('students.index'));

        $this->assertDatabaseHas('students', [
            'school_id' => $school->id,
            'admission_number' => 'BMK-001',
            'first_name' => 'Asha',
            'last_name' => 'Selemani',
            'gender' => 'female',
            'class_name' => 'Form II',
            'stream' => 'A',
        ]);
    }

    public function test_smart_school_import_requires_configured_source(): void
    {
        $school = $this->school('BMK');
        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $this->post(route('students.import.smart-school'), ['duplicate_mode' => 'skip'])
            ->assertSessionHasErrors('smart_school');
    }
}
