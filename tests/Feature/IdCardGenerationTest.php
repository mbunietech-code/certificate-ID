<?php

namespace Tests\Feature;

use App\Models\IdCard;
use App\Models\IdCardTemplate;
use App\Models\PrintJob;
use App\Models\Role;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdCardGenerationTest extends TestCase
{
    public function test_bulk_generation_issues_numbered_cards_and_builds_a_pdf(): void
    {
        $school = $this->school('BNG');
        $year = $this->currentYear($school);
        // Students without entry/completion years exercise the academic-year fallback in {year_range}.
        $students = Student::factory()->count(12)->for($school)->create(['academic_year_id' => $year->id, 'class_name' => 'Form IV']);
        Student::factory()->for($school)->create(['class_name' => 'Form I']);
        $template = $this->idTemplate($school);
        $admin = $this->userFor($school);

        $response = $this->actingAs($admin)->post(route('id-cards.store'), [
            'template_id' => $template->id, 'holder_type' => 'student', 'select_all' => 1,
            'filters' => ['class_name' => 'Form IV'], 'mode' => 'reuse', 'academic_year_id' => $year->id,
            'layout' => 'card', 'include_back' => 1,
        ]);

        $job = PrintJob::firstOrFail();
        $response->assertRedirect(route('print.show', $job));
        $this->assertSame('completed', $job->status, (string) $job->error_message);
        $this->assertSame(12, $job->total_items);
        $this->assertSame(12, $job->completed_items);
        $this->assertSame(0, $job->failed_items);
        Storage::disk('local')->assertExists($job->file_path);
        $this->assertStringStartsWith('%PDF', Storage::disk('local')->get($job->file_path));

        $numbers = IdCard::orderBy('id')->pluck('card_number')->all();
        $this->assertSame('BNG/2026/0001', $numbers[0]);
        $this->assertSame('BNG/2026/0012', $numbers[11]);
        $this->assertCount(12, array_unique($numbers));
        $this->assertSame(12, IdCard::whereIn('holder_id', $students->pluck('id'))->whereDate('expires_at', '2026-12-31')->count());

        // Browser print view and PDF download are logged and counted.
        $this->get(route('print.browser', $job))->assertOk()->assertSee('BNG/2026/0001')->assertSee($students[0]->last_name);
        $this->get(route('print.pdf', $job))->assertOk()->assertHeader('content-type', 'application/pdf');
        $frontPng = $this->get(route('id-cards.front-png', IdCard::firstOrFail()));
        $frontPng->assertOk()->assertHeader('content-type', 'image/png');
        $this->assertStringStartsWith("\x89PNG\r\n\x1a\n", $frontPng->getContent());
        $this->assertSame(2, $job->fresh()->print_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'print.browser', 'entity_id' => $job->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'id_card.generated']);
    }

    public function test_reuse_mode_reprints_existing_numbers_and_new_mode_replaces_them(): void
    {
        $school = $this->school('BNG');
        $year = $this->currentYear($school);
        $students = Student::factory()->count(3)->for($school)->create(['academic_year_id' => $year->id]);
        $template = $this->idTemplate($school);
        $this->actingAs($this->userFor($school));

        $payload = fn (string $mode) => [
            'template_id' => $template->id, 'holder_type' => 'student', 'ids' => $students->pluck('id')->all(),
            'mode' => $mode, 'academic_year_id' => $year->id, 'layout' => 'sheet', 'crop_marks' => 1, 'paper' => 'A4',
        ];

        $this->post(route('id-cards.store'), $payload('reuse'));
        $this->post(route('id-cards.store'), $payload('reuse'));
        $this->assertSame(3, IdCard::count(), 'Reprinting must not create new cards.');

        $this->post(route('id-cards.store'), $payload('new'));
        $this->assertSame(6, IdCard::count());
        $this->assertSame(3, IdCard::where('status', 'replaced')->count());
        $this->assertSame(['BNG/2026/0004', 'BNG/2026/0005', 'BNG/2026/0006'], IdCard::where('status', 'active')->orderBy('id')->pluck('card_number')->all());
        $this->assertSame(3, PrintJob::where('status', 'completed')->count());
    }

    public function test_staff_cards_use_the_staff_numbering_format_and_template_type_is_enforced(): void
    {
        $school = $this->school('BWM');
        $staff = Staff::factory()->count(2)->for($school)->create();
        $staffTemplate = $this->idTemplate($school, IdCardTemplate::TYPE_STAFF);
        $studentTemplate = $this->idTemplate($school, IdCardTemplate::TYPE_STUDENT);
        $this->actingAs($this->userFor($school));

        $this->post(route('id-cards.store'), [
            'template_id' => $studentTemplate->id, 'holder_type' => 'staff', 'ids' => $staff->pluck('id')->all(), 'mode' => 'new', 'layout' => 'card',
        ])->assertStatus(422);

        $this->post(route('id-cards.store'), [
            'template_id' => $staffTemplate->id, 'holder_type' => 'staff', 'ids' => $staff->pluck('id')->all(), 'mode' => 'new', 'layout' => 'card',
        ])->assertRedirect();

        $this->assertSame(['BWM/STF/2026/0001', 'BWM/STF/2026/0002'], IdCard::orderBy('id')->pluck('card_number')->all());
        $this->assertSame(PrintJob::TYPE_STAFF_ID, PrintJob::first()->type);
    }

    public function test_preview_renders_without_issuing_cards(): void
    {
        $school = $this->school('BNG');
        $students = Student::factory()->count(2)->for($school)->create();
        $template = $this->idTemplate($school);

        $this->actingAs($this->userFor($school))->post(route('id-cards.preview'), [
            'template_id' => $template->id, 'holder_type' => 'student', 'ids' => $students->pluck('id')->all(), 'mode' => 'reuse', 'layout' => 'card',
        ])->assertOk()->assertSee($students[0]->last_name)->assertSee('BNG/2026/0001');

        $this->assertSame(0, IdCard::count());
        $this->assertSame(0, PrintJob::count());
    }

    public function test_revoked_card_cannot_be_reprinted_and_viewer_cannot_generate(): void
    {
        $school = $this->school('BNG');
        $student = Student::factory()->for($school)->create();
        $template = $this->idTemplate($school);
        $admin = $this->userFor($school);
        $this->actingAs($admin)->post(route('id-cards.store'), [
            'template_id' => $template->id, 'holder_type' => 'student', 'ids' => [$student->id], 'mode' => 'new', 'layout' => 'card',
        ]);
        $card = IdCard::firstOrFail();

        $this->post(route('id-cards.revoke', $card), ['reason' => 'Lost'])->assertRedirect();
        $this->assertSame('revoked', $card->fresh()->status);
        $this->post(route('id-cards.reprint'), ['ids' => [$card->id], 'layout' => 'card'])->assertSessionHas('error');

        $viewer = $this->userFor($school, Role::VIEWER);
        $this->actingAs($viewer)->get(route('id-cards.generate'))->assertForbidden();
        $this->actingAs($viewer)->post(route('id-cards.store'), [])->assertForbidden();
    }
}
