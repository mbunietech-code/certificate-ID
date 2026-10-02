<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\PrintJob;
use App\Models\Role;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\Documents\CertificateIssuer;
use App\Services\Documents\IdCardIssuer;
use App\Services\Printing\PrintJobService;
use Tests\TestCase;

/**
 * CRITICAL: a school admin must never reach another school's data, even by
 * changing ids in URLs or forms. Tested in both directions.
 */
class SchoolIsolationTest extends TestCase
{
    /** @var array<string, array<string, mixed>> */
    private array $data = [];

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['BNG' => 'Bangulo Secondary School', 'BWM' => 'Benjamin William Mkapa High School'] as $code => $name) {
            $school = $this->school($code, $name);
            $students = Student::factory()->count(3)->for($school)->create(['academic_year_id' => $this->currentYear($school)->id]);
            $staff = Staff::factory()->for($school)->create();
            $template = $this->idTemplate($school, IdCardTemplate::TYPE_STUDENT, "{$code} card");
            $certTemplate = $this->certificateTemplate($school, "{$code} certificate");
            $admin = $this->userFor($school, Role::SCHOOL_ADMIN);
            $otherUser = $this->userFor($school, Role::VIEWER);

            [$card, $certificate, $job] = $this->asTenant($school, function () use ($students, $template, $certTemplate, $school, $admin) {
                $card = app(IdCardIssuer::class)->issue($students[0], $template, $this->currentYear($school));
                $certificate = app(CertificateIssuer::class)->issue($students[0], $certTemplate, ['title' => 'Award', 'issued_on' => '2026-06-01'], null);
                $job = app(PrintJobService::class)->createReprint($school, $admin, PrintJob::TYPE_STUDENT_ID, $template, collect([$card]), ['layout' => 'card'], '127.0.0.1');

                return [$card, $certificate, $job];
            });

            $this->data[$code] = compact('school', 'students', 'staff', 'template', 'certTemplate', 'admin', 'otherUser', 'card', 'certificate', 'job');
        }
    }

    public function test_bangulo_admin_cannot_access_benjamin_mkapa_data(): void
    {
        $this->assertIsolated('BNG', 'BWM');
    }

    public function test_benjamin_mkapa_admin_cannot_access_bangulo_data(): void
    {
        $this->assertIsolated('BWM', 'BNG');
    }

    private function assertIsolated(string $own, string $other): void
    {
        $me = $this->data[$own];
        $them = $this->data[$other];
        $this->actingAs($me['admin']);

        // Lists only contain the admin's own school.
        $this->get(route('students.index'))->assertOk()
            ->assertSee($me['students'][0]->admission_number)
            ->assertDontSee($them['students'][0]->admission_number);
        $this->get(route('staff.index'))->assertOk()->assertDontSee($them['staff']->employee_number);
        $this->get(route('id-cards.index'))->assertOk()->assertSee($me['card']->card_number)->assertDontSee($them['card']->card_number);
        $this->get(route('certificates.index'))->assertOk()->assertDontSee($them['certificate']->certificate_number);
        $this->get(route('print.history'))->assertOk()->assertDontSee($them['job']->job_number);
        $this->get(route('templates.id-cards.index'))->assertOk()->assertDontSee($them['template']->name);

        // Manually changing ids in URLs → 404 (record does not exist for this school).
        $student = $them['students'][0];
        $this->get(route('students.show', $student))->assertNotFound();
        $this->get(route('students.edit', $student))->assertNotFound();
        $this->put(route('students.update', $student), ['first_name' => 'Hacked'])->assertNotFound();
        $this->delete(route('students.destroy', $student))->assertNotFound();
        $this->post(route('students.restore', $student->id))->assertNotFound();
        $this->get(route('staff.show', $them['staff']))->assertNotFound();
        $this->put(route('staff.update', $them['staff']), [])->assertNotFound();
        $this->get(route('id-cards.show', $them['card']))->assertNotFound();
        $this->post(route('id-cards.revoke', $them['card']), ['reason' => 'x'])->assertNotFound();
        $this->get(route('certificates.show', $them['certificate']))->assertNotFound();
        $this->post(route('certificates.revoke', $them['certificate']), ['reason' => 'x'])->assertNotFound();
        $this->get(route('print.show', $them['job']))->assertNotFound();
        $this->get(route('print.browser', $them['job']))->assertNotFound();
        $this->get(route('print.pdf', $them['job']))->assertNotFound();
        $this->get(route('print.status', $them['job']))->assertNotFound();
        $this->get(route('templates.id-cards.design', $them['template']))->assertNotFound();
        $this->put(route('templates.id-cards.design.save', $them['template']), ['design' => []])->assertNotFound();
        $this->get(route('templates.certificates.preview', $them['certTemplate']))->assertNotFound();
        $this->get(route('academic-years.edit', $this->currentYear($them['school'])))->assertNotFound();

        // Users of the other school: forbidden.
        $this->get(route('users.edit', $them['otherUser']))->assertForbidden();
        $this->put(route('users.update', $them['otherUser']), [])->assertForbidden();
        $this->get(route('users.index'))->assertDontSee($them['otherUser']->email);

        // School management is super-admin only.
        $this->get(route('schools.show', $them['school']))->assertForbidden();
        $this->get(route('schools.edit', $them['school']))->assertForbidden();

        // Forged ids in form bodies are ignored.
        $this->post(route('students.bulk'), ['ids' => $them['students']->pluck('id')->all(), 'action' => 'delete'])->assertRedirect();
        $this->assertSame(3, Student::forSchool($them['school']->id)->count());

        $this->post(route('id-cards.store'), [
            'template_id' => $me['template']->id, 'holder_type' => 'student', 'ids' => $them['students']->pluck('id')->all(),
            'mode' => 'new', 'layout' => 'card',
        ])->assertSessionHasErrors('ids');

        $this->post(route('id-cards.store'), [
            'template_id' => $them['template']->id, 'holder_type' => 'student', 'ids' => $me['students']->pluck('id')->all(),
            'mode' => 'new', 'layout' => 'card',
        ])->assertNotFound();

        $this->post(route('id-cards.reprint'), ['ids' => [$them['card']->id], 'layout' => 'card'])
            ->assertSessionHas('error');

        // Another school's ids never reach the database through a mass-assigned school_id.
        $this->post(route('students.store'), [
            'school_id' => $them['school']->id, 'admission_number' => 'X-1', 'first_name' => 'A', 'last_name' => 'B',
            'gender' => 'male', 'class_name' => 'Form I', 'status' => 'active',
        ])->assertRedirect();
        $this->assertSame($me['school']->id, Student::withoutGlobalScopes()->where('admission_number', 'X-1')->value('school_id'));

        // Nothing of the other school changed.
        $this->assertSame('active', IdCard::withoutGlobalScopes()->find($them['card']->id)->status);
        $this->assertSame('valid', Certificate::withoutGlobalScopes()->find($them['certificate']->id)->status);
        $this->assertNotSame('Hacked', Student::withoutGlobalScopes()->find($student->id)->first_name);
    }

    public function test_global_templates_are_shared_but_read_only_for_school_admins(): void
    {
        $global = $this->idTemplate(null, IdCardTemplate::TYPE_STUDENT, 'Shared global card');
        $this->actingAs($this->data['BNG']['admin']);

        $this->get(route('templates.id-cards.index'))->assertSee('Shared global card');
        $this->get(route('templates.id-cards.preview', $global))->assertOk();
        $this->get(route('templates.id-cards.design', $global))->assertForbidden();
        $this->delete(route('templates.id-cards.destroy', $global))->assertForbidden();

        // ...but can be copied into the school.
        $this->post(route('templates.id-cards.duplicate', $global))->assertRedirect();
        $this->assertDatabaseHas('id_card_templates', ['name' => 'Shared global card (copy)', 'school_id' => $this->data['BNG']['school']->id]);
    }

    public function test_super_admin_sees_all_schools_and_can_narrow_to_one(): void
    {
        $super = $this->userFor(null);
        $this->actingAs($super);

        $this->get(route('students.index'))->assertOk()
            ->assertSee($this->data['BNG']['students'][0]->admission_number)
            ->assertSee($this->data['BWM']['students'][0]->admission_number);

        $this->post(route('context.switch'), ['school_id' => $this->data['BWM']['school']->id])->assertRedirect();
        $this->get(route('students.index'))->assertOk()
            ->assertSee($this->data['BWM']['students'][0]->admission_number)
            ->assertDontSee($this->data['BNG']['students'][0]->admission_number);
    }

    public function test_school_users_cannot_switch_school_context(): void
    {
        $this->actingAs($this->data['BNG']['admin']);

        $this->post(route('context.switch'), ['school_id' => $this->data['BWM']['school']->id])->assertForbidden();
        $this->withSession(['active_school_id' => $this->data['BWM']['school']->id])
            ->get(route('students.index'))->assertDontSee($this->data['BWM']['students'][0]->admission_number);
    }

    public function test_users_of_a_deactivated_school_are_signed_out(): void
    {
        $admin = $this->data['BNG']['admin'];
        School::whereKey($this->data['BNG']['school']->id)->update(['status' => 'inactive']);

        $this->actingAs($admin)->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_school_admin_cannot_create_users_for_another_school_or_super_admins(): void
    {
        $this->actingAs($this->data['BNG']['admin']);
        $superRole = Role::where('slug', Role::SUPER_ADMIN)->value('id');
        $viewerRole = Role::where('slug', Role::VIEWER)->value('id');

        $this->post(route('users.store'), [
            'name' => 'Evil', 'email' => 'evil@x.test', 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'role_id' => $superRole, 'status' => 'active',
        ])->assertSessionHasErrors('role_id');

        $this->post(route('users.store'), [
            'name' => 'Sneaky', 'email' => 'sneaky@x.test', 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'role_id' => $viewerRole, 'school_id' => $this->data['BWM']['school']->id, 'status' => 'active',
        ])->assertSessionHasErrors('school_id');

        $this->post(route('users.store'), [
            'name' => 'Ok', 'email' => 'ok@x.test', 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
            'role_id' => $viewerRole, 'status' => 'active',
        ])->assertRedirect(route('users.index'));
        $this->assertSame($this->data['BNG']['school']->id, User::where('email', 'ok@x.test')->value('school_id'));
    }
}
