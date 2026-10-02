<?php

namespace Tests\Feature;

use App\Models\PrintJob;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use App\Services\Documents\NumberGenerator;
use Tests\TestCase;

class PrintHistoryTest extends TestCase
{
    public function test_history_lists_filters_and_exports_jobs(): void
    {
        $school = $this->school('BNG');
        $students = Student::factory()->count(2)->for($school)->create();
        $template = $this->idTemplate($school);
        $certTemplate = $this->certificateTemplate($school);
        $admin = $this->userFor($school);
        $printer = $this->userFor($school, Role::PRINTER);

        $this->actingAs($admin)->post(route('id-cards.store'), ['template_id' => $template->id, 'holder_type' => 'student',
            'ids' => $students->pluck('id')->all(), 'mode' => 'new', 'layout' => 'card']);
        $this->actingAs($admin)->post(route('certificates.store'), ['template_id' => $certTemplate->id, 'title' => 'Award',
            'issued_on' => '2026-05-01', 'ids' => [$students[0]->id]]);

        $this->assertSame(2, PrintJob::count());
        $idJob = PrintJob::where('type', PrintJob::TYPE_STUDENT_ID)->first();

        $this->actingAs($printer)->get(route('print.history'))->assertOk()
            ->assertSee($idJob->job_number)->assertSee('127.0.0.1');
        $this->actingAs($printer)->get(route('print.history', ['type' => 'certificate']))->assertOk()->assertDontSee($idJob->job_number);
        $this->actingAs($printer)->get(route('print.history', ['user_id' => $printer->id]))->assertDontSee($idJob->job_number);

        $csv = $this->actingAs($printer)->get(route('print.history.export', ['format' => 'csv']))->assertOk()->streamedContent();
        $this->assertStringContainsString($idJob->job_number, $csv);

        $this->actingAs($printer)->get(route('print.status', $idJob))->assertOk()->assertJson(['status' => 'completed', 'percent' => 100, 'has_pdf' => true]);
    }

    public function test_number_formats_are_validated_and_rendered(): void
    {
        $this->assertNotNull(NumberGenerator::validateFormat('{CODE}/{YEAR}'));
        $this->assertNotNull(NumberGenerator::validateFormat('{CODE}/{FOO}/{SEQ}'));
        $this->assertNull(NumberGenerator::validateFormat('{CODE}/CERT/{YEAR}/{SEQ:5}'));
        $this->assertSame('BWM/CERT/2026/00007', NumberGenerator::preview('{CODE}/CERT/{YEAR}/{SEQ:5}', 'bwm', '2026', 7));

        $school = School::factory()->create(['school_code' => 'BNG']);
        $generator = app(NumberGenerator::class);
        $taken = ['BNG/2026/0002'];
        $first = $generator->next($school, 'test', '{CODE}/{YEAR}/{SEQ:4}', '2026', fn ($n) => in_array($n, $taken, true));
        $second = $generator->next($school, 'test', '{CODE}/{YEAR}/{SEQ:4}', '2026', fn ($n) => in_array($n, $taken, true));
        $nextYear = $generator->next($school, 'test', '{CODE}/{YEAR}/{SEQ:4}', '2027', fn () => false);

        $this->assertSame('BNG/2026/0001', $first);
        $this->assertSame('BNG/2026/0003', $second, 'Numbers already taken are skipped, never duplicated.');
        $this->assertSame('BNG/2027/0001', $nextYear, 'Sequences reset per year.');
    }
}
