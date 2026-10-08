<?php

namespace Tests\Feature;

use App\Models\IdCard;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentImport;
use App\Services\Documents\IdCardIssuer;
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
        $this->assertSame([455, 488], [$w, $h], 'ID photos are always stored at exactly 455 × 488 px.');

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

    public function test_admission_numbers_may_repeat_and_default_to_placeholder(): void
    {
        $bng = $this->school('BNG');
        Student::factory()->for($bng)->create(['admission_number' => 'ADM-2']);

        $this->actingAs($this->userFor($bng));
        $this->post(route('students.store'), $this->payload(['admission_number' => 'ADM-2']))->assertSessionHasNoErrors();
        $this->post(route('students.store'), $this->payload(['admission_number' => '']))->assertSessionHasNoErrors();
        $this->post(route('students.store'), $this->payload(['admission_number' => '  ']))->assertSessionHasNoErrors();

        $this->assertSame(2, Student::where('admission_number', 'ADM-2')->count());
        $this->assertSame(2, Student::where('admission_number', Student::NO_ADMISSION_NUMBER)->count());
    }

    public function test_benja_student_form_uses_level_based_classes_and_hides_contacts(): void
    {
        $school = $this->school('BWMHS');
        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $this->get(route('students.create'))->assertOk()
            ->assertSee('Select level')
            ->assertSee('PCM')
            ->assertDontSee('Date of birth')
            ->assertDontSee('Nationality')
            ->assertDontSee('Parent / guardian')
            ->assertDontSee('Student phone');

        $this->post(route('students.store'), $this->payload([
            'level' => 'O-Level',
            'class_name' => '',
            'stream' => 'A',
            'combination' => 'PCM',
            'date_of_birth' => '2010-03-14',
            'nationality' => 'Tanzanian',
            'parent_name' => 'Hidden Parent',
            'parent_phone' => '+255700000001',
            'student_phone' => '+255700000002',
            'address' => 'Hidden address',
        ]))->assertRedirect();

        $student = Student::firstOrFail();
        $this->assertSame('O-Level', $student->level);
        $this->assertSame('Form I - IV', $student->class_name, 'O-Level has no individual class.');
        $this->assertNull($student->date_of_birth);
        $this->assertNull($student->nationality);
        $this->assertNull($student->stream);
        $this->assertNull($student->combination);
        $this->assertNull($student->parent_name);
        $this->assertNull($student->parent_phone);
        $this->assertNull($student->student_phone);
        $this->assertNull($student->address);
    }

    public function test_benja_a_level_students_choose_a_valid_combination(): void
    {
        $school = $this->school('BWM');
        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $this->post(route('students.store'), $this->payload([
            'level' => 'A-Level',
            'class_name' => 'Form VI',
            'combination' => '',
        ]))->assertSessionHasErrors('combination');

        $this->post(route('students.store'), $this->payload([
            'level' => 'A-Level',
            'class_name' => '',
            'combination' => 'PCM',
        ]))->assertSessionHasErrors('class_name');

        $this->post(route('students.store'), $this->payload([
            'admission_number' => 'BWM/2026/0002',
            'level' => 'A-Level',
            'class_name' => 'Form VI',
            'combination' => 'PCM',
            'stream' => 'A',
        ]))->assertRedirect();

        $student = Student::where('admission_number', 'BWM/2026/0002')->firstOrFail();
        $this->assertSame('A-Level', $student->level);
        $this->assertSame('Form VI', $student->class_name);
        $this->assertSame('PCM', $student->combination);
        $this->assertNull($student->stream);
    }

    public function test_photos_of_any_shape_become_455_by_488_and_old_photos_can_be_normalized(): void
    {
        $school = $this->school('BNG');
        $this->actingAs($this->userFor($school));

        // A wide landscape photo (e.g. from a phone) is cropped, not squashed.
        $this->post(route('staff.store'), [
            'employee_number' => 'EMP-9', 'first_name' => 'Daudi', 'last_name' => 'Kimaro', 'gender' => 'male', 'employment_status' => 'active',
            'photo' => UploadedFile::fake()->image('wide.jpg', 1600, 900),
        ])->assertRedirect();
        $this->assertSame([455, 488], array_slice(getimagesizefromstring(Storage::disk('public')->get(Staff::firstOrFail()->photo_path)), 0, 2));

        // A photo saved before the rule (wrong size) is fixed by the command.
        $old = Student::factory()->for($school)->create();
        Storage::disk('public')->put('students/old.jpg', UploadedFile::fake()->image('old.jpg', 300, 300)->getContent());
        $old->forceFill(['photo_path' => 'students/old.jpg'])->saveQuietly();

        $this->artisan('photos:normalize')->assertSuccessful();

        $path = $old->fresh()->photo_path;
        $this->assertNotSame('students/old.jpg', $path);
        Storage::disk('public')->assertMissing('students/old.jpg');
        $this->assertSame([455, 488], array_slice(getimagesizefromstring(Storage::disk('public')->get($path)), 0, 2));
    }

    public function test_taken_ids_leave_the_waiting_list_and_can_be_undone(): void
    {
        $school = $this->school('BWM');
        $done = Student::factory()->for($school)->create(['first_name' => 'Taken', 'middle_name' => null, 'last_name' => 'Already', 'class_name' => 'Form I']);
        $waiting = Student::factory()->for($school)->create(['first_name' => 'Still', 'middle_name' => null, 'last_name' => 'Waiting', 'class_name' => 'Form VI']);
        $printer = $this->userFor($school, Role::PRINTER);

        $this->actingAs($printer)->post(route('students.id-taken', $done))->assertRedirect();
        $this->assertNotNull($done->fresh()->id_taken_at);
        $this->assertSame($printer->id, $done->fresh()->id_taken_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.id_taken', 'entity_id' => $done->id]);

        // Admission numbers (not names: the success message after "Taken" also shows the name).
        $this->get(route('students.index'))->assertOk()
            ->assertSeeInOrder([$waiting->admission_number, $done->admission_number], 'Students waiting for their ID are listed first.');
        $this->get(route('students.index', ['id_status' => 'waiting']))->assertOk()
            ->assertSee($waiting->admission_number)->assertDontSee($done->admission_number);
        $this->get(route('students.index', ['id_status' => 'taken']))->assertOk()
            ->assertSee($done->admission_number)->assertDontSee($waiting->admission_number);

        // Undo puts the student back in the waiting list.
        $this->post(route('students.id-taken', $done), ['taken' => 0])->assertRedirect();
        $this->assertNull($done->fresh()->id_taken_at);

        // Bulk "Mark ID taken".
        $this->post(route('students.bulk'), ['ids' => [$done->id, $waiting->id], 'action' => 'id_taken'])->assertRedirect();
        $this->assertSame(0, Student::query()->filter(['id_status' => 'waiting'])->count());
    }

    public function test_taken_id_counts_as_printed_on_the_card_and_dashboard(): void
    {
        $school = $this->school('BWM');
        $withCard = Student::factory()->for($school)->create();
        $withoutCard = Student::factory()->for($school)->create();
        Student::factory()->for($school)->create();
        $card = new IdCard;
        $card->forceFill([
            'school_id' => $school->id, 'holder_type' => $withCard->getMorphClass(), 'holder_id' => $withCard->id,
            'card_number' => 'BWM/2026/0001', 'verification_code' => 'CODE0001', 'issued_at' => now(),
        ])->save();
        $this->actingAs($this->userFor($school));

        $this->post(route('students.bulk'), ['ids' => [$withCard->id, $withoutCard->id], 'action' => 'id_taken'])->assertRedirect();

        $this->assertSame(1, $card->fresh()->print_count);
        $this->assertNotNull($card->fresh()->last_printed_at);
        $this->get(route('dashboard'))->assertOk()->assertViewHas('stats', fn (array $stats) => $stats['ids_printed'] === 2)
            ->assertSee('IDs printed')->assertSee('IDs not printed yet');

        $this->actingAs($this->userFor(null, Role::SUPER_ADMIN))->get(route('schools.show', $school))->assertOk()
            ->assertViewHas('counts', fn (array $counts) => $counts['ids_printed'] === 2 && $counts['id_cards'] === 1);
    }

    public function test_only_permitted_users_of_the_same_school_can_mark_ids_taken(): void
    {
        $bwm = $this->school('BWM');
        $bng = $this->school('BNG');
        $student = Student::factory()->for($bwm)->create();

        $this->actingAs($this->userFor($bwm, Role::VIEWER))->post(route('students.id-taken', $student))->assertForbidden();
        $this->actingAs($this->userFor($bng, Role::SCHOOL_ADMIN))->post(route('students.id-taken', $student))->assertNotFound();
        $this->assertNull($student->fresh()->id_taken_at);
    }

    public function test_purge_command_permanently_removes_only_the_chosen_schools_students(): void
    {
        $bwm = $this->school('BWM');
        $bng = $this->school('BNG');
        $template = $this->idTemplate($bwm);
        $doomed = Student::factory()->count(2)->for($bwm)->create();
        $kept = Student::factory()->for($bng)->create();
        Storage::disk('public')->put('students/demo.jpg', 'x');
        $doomed[0]->forceFill(['photo_path' => 'students/demo.jpg'])->saveQuietly();
        $doomed[1]->delete(); // already soft-deleted students are purged too
        $this->asTenant($bwm, fn () => app(IdCardIssuer::class)->issue($doomed[0], $template, null));

        // Nothing happens without confirmation.
        $this->artisan('students:purge', ['--school' => ['BWM']])
            ->expectsConfirmation('Type "yes" to delete them permanently', 'no')->assertSuccessful();
        $this->assertSame(2, Student::withTrashed()->where('school_id', $bwm->id)->count());

        $this->artisan('students:purge', ['--school' => ['BWM'], '--reset-numbering' => true])
            ->expectsConfirmation('Type "yes" to delete them permanently', 'yes')->assertSuccessful();

        $this->assertSame(0, Student::withTrashed()->where('school_id', $bwm->id)->count());
        $this->assertSame(0, IdCard::withoutGlobalScopes()->where('school_id', $bwm->id)->count());
        $this->assertDatabaseMissing('number_sequences', ['school_id' => $bwm->id, 'type' => 'student_id']);
        Storage::disk('public')->assertMissing('students/demo.jpg');
        $this->assertNotNull(Student::withoutGlobalScopes()->find($kept->id), 'Other schools are untouched.');
        $this->assertDatabaseHas('audit_logs', ['action' => 'student.purged', 'school_id' => $bwm->id]);

        // Admission numbers can be reused after a purge.
        $this->actingAs($this->userFor($bwm))->post(route('students.store'), $this->payload([
            'admission_number' => $doomed[0]->admission_number, 'level' => 'O-Level',
        ]))->assertSessionHasNoErrors();
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

    public function test_waiting_students_are_listed_newest_registered_or_edited_first(): void
    {
        $school = $this->school('BNG');
        $older = Student::factory()->for($school)->create(['admission_number' => 'OLDER-1', 'class_name' => 'Form I']);
        $this->travel(1)->minutes();
        $newer = Student::factory()->for($school)->create(['admission_number' => 'NEWER-1', 'class_name' => 'Form IV']);
        $this->actingAs($this->userFor($school));

        $this->get(route('students.index'))->assertSeeInOrder([$newer->admission_number, $older->admission_number]);

        $this->travel(1)->minutes();
        $this->put(route('students.update', $older), $this->payload(['admission_number' => 'OLDER-1', 'class_name' => 'Form I']))->assertRedirect();
        $this->get(route('students.index'))->assertSeeInOrder([$older->admission_number, $newer->admission_number], 'An edited student moves to the top.');
    }

    public function test_export_can_be_limited_to_selected_students(): void
    {
        $bng = $this->school('BNG');
        $bwm = $this->school('BWM');
        $picked = Student::factory()->for($bng)->create(['admission_number' => 'PICKED-1']);
        Student::factory()->for($bng)->create(['admission_number' => 'NOT-PICKED']);
        $other = Student::factory()->for($bwm)->create(['admission_number' => 'BWM-SECRET']);
        $this->actingAs($this->userFor($bng));

        $csv = $this->get(route('students.export', ['format' => 'csv', 'ids' => [$picked->id, $other->id]]))->assertOk()->streamedContent();
        $this->assertStringContainsString('PICKED-1', $csv);
        $this->assertStringNotContainsString('NOT-PICKED', $csv);
        $this->assertStringNotContainsString('BWM-SECRET', $csv, 'Selecting another school\'s student id exports nothing of theirs.');

        $this->get(route('students.export', ['format' => 'pdf', 'ids' => [$picked->id]]))->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_import_previews_validates_and_only_imports_valid_rows(): void
    {
        $school = $this->school('BNG');
        Student::factory()->for($school)->create(['admission_number' => 'EXIST-1', 'first_name' => 'Old']);
        Student::factory()->for($school)->create(['admission_number' => Student::NO_ADMISSION_NUMBER, 'first_name' => 'Placeholder']);
        $this->actingAs($this->userFor($school, Role::REGISTRAR));

        $csv = implode("\n", [
            'Admission Number,First Name,Middle Name,Last Name,Gender,Date of Birth,Level,Class,Stream,Academic Year,Graduation Year',
            'NEW-1,Amina,,Hassan,F,2010-03-14,o level,Form IV,A,2026,2026',
            'NEW-2,Baraka,,Mwita,Male,14/07/2009,A-Level,Form VI,,,2026',
            'EXIST-1,Updated,,Name,M,,O-Level,Form II,B,,',
            'BAD-1,,,NoFirst,X,31/02/2010,,Form I,,1999,',
            'NEW-1,Same,,Number,F,,,Form I,,,',
            ',No,,Number,M,,,Form I,,,',
            ',Also,,Blank,F,,,Form II,,,',
        ]);
        $file = UploadedFile::fake()->createWithContent('students.csv', $csv);

        $response = $this->post(route('students.import.store'), ['file' => $file, 'duplicate_mode' => 'skip']);
        $import = StudentImport::firstOrFail();
        $response->assertRedirect(route('students.import.show', $import));
        $this->assertSame(['total' => 7, 'valid' => 5, 'duplicate' => 1, 'invalid' => 1], $import->summary);
        $this->assertSame(2, Student::count(), 'Nothing is imported before confirmation.');

        $this->get(route('students.import.show', $import))->assertOk()->assertSee('will NOT be imported');

        $this->post(route('students.import.confirm', $import))->assertRedirect(route('students.index'));
        $this->assertSame(7, Student::count());
        $this->assertSame(2, Student::where('admission_number', 'NEW-1')->count(), 'A number repeated in the file is another student.');
        $this->assertSame(3, Student::where('admission_number', Student::NO_ADMISSION_NUMBER)->count(), 'Blank numbers never match existing students.');
        $this->assertSame('O-Level', Student::where('admission_number', 'NEW-1')->where('first_name', 'Amina')->value('level'));
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
