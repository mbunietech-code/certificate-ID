<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\PrintJob;
use App\Models\Role;
use App\Models\School;
use App\Models\Student;
use Tests\TestCase;

class CertificateGenerationTest extends TestCase
{
    public function test_bulk_certificates_get_unique_sequential_numbers_per_school(): void
    {
        $bng = $this->school('BNG');
        $bwm = $this->school('BWM');
        $bngStudents = Student::factory()->count(5)->for($bng)->create();
        $bwmStudents = Student::factory()->count(2)->for($bwm)->create();
        $global = $this->certificateTemplate(null, 'Global certificate');

        $details = ['template_id' => $global->id, 'title' => 'Certificate of Completion', 'program' => 'CSEE 2026',
            'description' => 'has successfully completed Form IV.', 'issued_on' => '2026-11-20'];

        $this->actingAs($this->userFor($bng, Role::REGISTRAR))
            ->post(route('certificates.store'), $details + ['ids' => $bngStudents->pluck('id')->all()])->assertRedirect();
        $this->actingAs($this->userFor($bwm, Role::REGISTRAR))
            ->post(route('certificates.store'), $details + ['ids' => $bwmStudents->pluck('id')->all()])->assertRedirect();

        $this->assertSame(
            ['BNG/CERT/2026/00001', 'BNG/CERT/2026/00002', 'BNG/CERT/2026/00003', 'BNG/CERT/2026/00004', 'BNG/CERT/2026/00005'],
            Certificate::withoutGlobalScopes()->where('school_id', $bng->id)->orderBy('id')->pluck('certificate_number')->all(),
        );
        $this->assertSame(['BWM/CERT/2026/00001', 'BWM/CERT/2026/00002'],
            Certificate::withoutGlobalScopes()->where('school_id', $bwm->id)->orderBy('id')->pluck('certificate_number')->all());

        $job = PrintJob::withoutGlobalScopes()->where('school_id', $bng->id)->firstOrFail();
        $this->assertSame('completed', $job->status, (string) $job->error_message);
        $certificate = Certificate::withoutGlobalScopes()->where('school_id', $bng->id)->first();
        $this->assertSame('CSEE 2026', $certificate->program);
        $this->assertSame(Student::withoutGlobalScopes()->find($certificate->student_id)->full_name, $certificate->recipient_name);
    }

    public function test_custom_numbering_format_is_used_and_never_duplicates(): void
    {
        $school = $this->school('BNG');
        School::whereKey($school->id)->update(['certificate_number_format' => 'BANGULO-{YY}-{SEQ:3}']);
        $students = Student::factory()->count(3)->for($school)->create();
        $template = $this->certificateTemplate($school);
        $this->actingAs($this->userFor($school));

        $this->post(route('certificates.store'), ['template_id' => $template->id, 'title' => 'Award', 'issued_on' => '2026-05-01',
            'ids' => $students->pluck('id')->all()]);

        // A second batch for the same students continues the sequence.
        $this->post(route('certificates.store'), ['template_id' => $template->id, 'title' => 'Award', 'issued_on' => '2026-05-02',
            'ids' => $students->pluck('id')->all()]);

        $numbers = Certificate::orderBy('id')->pluck('certificate_number')->all();
        $this->assertSame(['BANGULO-26-001', 'BANGULO-26-002', 'BANGULO-26-003', 'BANGULO-26-004', 'BANGULO-26-005', 'BANGULO-26-006'], $numbers);
    }

    public function test_printer_cannot_issue_certificates_but_can_reprint(): void
    {
        $school = $this->school('BNG');
        $student = Student::factory()->for($school)->create();
        $template = $this->certificateTemplate($school);
        $this->actingAs($this->userFor($school))->post(route('certificates.store'), [
            'template_id' => $template->id, 'title' => 'Award', 'issued_on' => '2026-05-01', 'ids' => [$student->id],
        ]);
        $certificate = Certificate::firstOrFail();

        $printer = $this->userFor($school, Role::PRINTER);
        $this->actingAs($printer)->get(route('certificates.generate'))->assertForbidden();
        $this->actingAs($printer)->post(route('certificates.reprint'), ['ids' => [$certificate->id]])->assertRedirect();
        $this->assertSame(2, PrintJob::count());
        $this->assertSame(1, Certificate::count(), 'A reprint must not issue a new number.');
    }
}
