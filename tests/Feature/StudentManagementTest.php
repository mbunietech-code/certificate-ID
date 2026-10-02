<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StudentManagementTest extends TestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'admission_number' => 'BNG/2026/0001', 'first_name' => 'Amina', 'middle_name' => 'Juma', 'last_name' => 'Hassan',
            'gender' => 'female', 'date_of_birth' => '2010-03-14', 'level' => 'O-Level', 'class_name' => 'Form IV', 'stream' => 'A',
            'entry_year' => 2023, 'completion_year' => 2026, 'status' => 'active',
        ], $overrides);
    }

    public function test_student_crud_with_photo_is_scoped_and_audited(): void
    {
        $school = $this->school('BNG');
        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $this->post(route('students.store'), $this->payload([
            'photo' => UploadedFile::fake()->image('photo.png', 1200, 1500),
            'academic_year_id' => $this->currentYear($school)->id,
        ]))->assertRedirect();

        $student = Student::firstOrFail();
        $this->assertSame($school->id, $student->school_id);
        $this->assertSame('2023 - 2026', $student->yearRange());
        Storage::disk('public')->assertExists($student->photo_path);
        $this->assertStringEndsWith('.jpg', $student->photo_path, 'Photos are re-encoded to JPEG.');
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($student->photo_path));
        $this->assertLessThanOrEqual(600, max($w, $h), 'Photos are resized.');

        $this->get(route('students.show', $student))->assertOk()->assertSee('Amina Juma Hassan');

        $this->put(route('students.update', $student), $this->payload(['first_name' => 'Aminah']))->assertRedirect();
        $this->assertSame('Aminah', $student->fresh()->first_name);

        $this->delete(route('students.destroy', $student))->assertRedirect();
        $this->assertSoftDeleted($student);
        $this->post(route('students.restore', $student->id))->assertRedirect();
        $this->assertNotSoftDeleted($student);

        foreach (['student.created', 'student.updated', 'student.deleted', 'student.restored'] as $action) {
            $this->assertDatabaseHas('audit_logs', ['action' => $action, 'entity_id' => $student->id]);
        }
    }

    public function test_admission_numbers_are_unique_per_school_only(): void
    {
        $bng = $this->school('BNG');
        $bwm = $this->school('BWM');
        Student::factory()->for($bwm)->create(['admission_number' => 'ADM-1']);
        Student::factory()->for($bng)->create(['admission_number' => 'ADM-2']);

        $this->actingAs($this->userFor($bng));
        $this->post(route('students.store'), $this->payload(['admission_number' => 'ADM-1']))->assertSessionHasNoErrors();
        $this->post(route('students.store'), $this->payload(['admission_number' => 'ADM-2']))->assertSessionHasErrors('admission_number');
    }

    public function test_invalid_uploads_are_rejected(): void
    {
        $school = $this->school('BNG');
        $this->actingAs($this->userFor($school));

        $this->post(route('students.store'), $this->payload(['photo' => UploadedFile::fake()->create('evil.php', 10, 'application/x-php')]))
            ->assertSessionHasErrors('photo');
        $this->post(route('students.store'), $this->payload(['photo' => UploadedFile::fake()->create('fake.png', 10, 'image/png')]))
            ->assertSessionHasErrors();
        $this->assertSame(0, Student::count());
    }

    public function test_search_filters_and_pagination(): void
    {
        $school = $this->school('BNG');
        Student::factory()->count(30)->for($school)->create(['class_name' => 'Form I']);
        Student::factory()->for($school)->create(['first_name' => 'Zawadi', 'last_name' => 'Mwakyusa', 'class_name' => 'Form IV', 'gender' => 'female']);
        $this->actingAs($this->userFor($school, Role::VIEWER));

        $this->get(route('students.index', ['search' => 'Zawadi Mwak']))->assertOk()->assertSee('Mwakyusa');
        $this->get(route('students.index', ['class_name' => 'Form IV', 'gender' => 'female']))->assertSee('Zawadi');
        $this->get(route('students.index', ['class_name' => 'Form I']))->assertDontSee('Zawadi')->assertSee('of <span class="font-medium text-slate-700">30</span>', false);
    }

    public function test_export_contains_only_own_school(): void
    {
        $bng = $this->school('BNG');
        $bwm = $this->school('BWM');
        Student::factory()->for($bng)->create(['admission_number' => 'BNG-EXPORT']);
        Student::factory()->for($bwm)->create(['admission_number' => 'BWM-SECRET']);

        $csv = $this->actingAs($this->userFor($bng))->get(route('students.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString('BNG-EXPORT', $csv);
        $this->assertStringNotContainsString('BWM-SECRET', $csv);
    }

    public function test_import_previews_validates_and_only_imports_valid_rows(): void
    {
        $school = $this->school('BNG');
        Student::factory()->for($school)->create(['admission_number' => 'EXIST-1', 'first_name' => 'Old']);
        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $csv = implode("\n", [
            'Admission Number,First Name,Middle Name,Last Name,Gender,Date of Birth,Level,Class,Stream,Academic Year,Graduation Year',
            'NEW-1,Amina,,Hassan,F,2010-03-14,o level,Form IV,A,2026,2026',
            'NEW-2,Baraka,,Mwita,Male,14/07/2009,A-Level,Form VI,,,2026',
            'EXIST-1,Updated,,Name,M,,O-Level,Form II,B,,',
            'BAD-1,,,NoFirst,X,31/02/2010,,Form I,,1999,',
            'NEW-1,Dup,,InFile,F,,,Form I,,,',
        ]);
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->post(route('students.import.store'), ['file' => $file, 'duplicate_mode' => 'skip']);
        $import = StudentImport::firstOrFail();
        $response->assertRedirect(route('students.import.show', $import));
        $this->assertSame(['total' => 5, 'valid' => 2, 'duplicate' => 1, 'invalid' => 2], $import->summary);
        $this->assertSame(1, Student::count(), 'Nothing is imported before confirmation.');

        $this->get(route('students.import.show', $import))->assertOk()->assertSee('will NOT be imported')
            ->assertSee('Duplicate admission number in file');

        $this->post(route('students.import.confirm', $import))->assertRedirect(route('students.index'));
        $this->assertSame(3, Student::count());
        $this->assertSame('O-Level', Student::where('admission_number', 'NEW-1')->value('level'));
        $this->assertSame('2024 - 2026', Student::where('admission_number', 'NEW-2')->first()->yearRange());
        $this->assertSame('Old', Student::where('admission_number', 'EXIST-1')->value('first_name'), 'Skip mode keeps existing records.');
        $this->assertSame('completed', $import->fresh()->status);

        $this->post(route('students.import.confirm', $import))->assertStatus(409);
    }

    public function test_staff_crud(): void
    {
        $school = $this->school('BNG');
        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $this->post(route('staff.store'), [
            'employee_number' => 'EMP-1', 'first_name' => 'Daudi', 'last_name' => 'Kimaro', 'gender' => 'male',
            'job_title' => 'Teacher', 'department' => 'Science', 'employment_status' => 'active',
        ])->assertRedirect();
        $staff = Staff::firstOrFail();
        $this->assertSame($school->id, $staff->school_id);

        $this->put(route('staff.update', $staff), [
            'employee_number' => 'EMP-1', 'first_name' => 'Daudi', 'last_name' => 'Kimaro', 'gender' => 'male',
            'job_title' => 'Head of Department', 'employment_status' => 'active',
        ])->assertRedirect();
        $this->assertSame('Head of Department', $staff->fresh()->job_title);

        $this->delete(route('staff.destroy', $staff))->assertRedirect();
        $this->assertSoftDeleted($staff);
    }
}
