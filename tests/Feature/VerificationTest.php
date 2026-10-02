<?php

namespace Tests\Feature;

use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\Student;
use App\Models\VerificationLog;
use App\Services\Documents\CertificateIssuer;
use App\Services\Documents\IdCardIssuer;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    private Certificate $certificate;

    private IdCard $card;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $school = $this->school('BWM', 'Benjamin William Mkapa High School');
        $this->student = Student::factory()->for($school)->create([
            'first_name' => 'Nasra', 'middle_name' => 'Saidi', 'last_name' => 'Juma',
            'date_of_birth' => '2008-03-14', 'parent_name' => 'Private Parent', 'parent_phone' => '+255 700 999 888',
        ]);
        $template = $this->idTemplate($school);
        $certTemplate = $this->certificateTemplate($school);

        [$this->card, $this->certificate] = $this->asTenant($school, fn () => [
            app(IdCardIssuer::class)->issue($this->student, $template, $this->currentYear($school)),
            app(CertificateIssuer::class)->issue($this->student, $certTemplate, ['title' => 'Certificate of Completion', 'issued_on' => '2026-11-01'], null),
        ]);
    }

    public function test_valid_certificate_shows_minimal_public_details(): void
    {
        $this->get(route('verify.certificate', $this->certificate->verification_code))
            ->assertOk()
            ->assertSee('Valid certificate')
            ->assertSee('Nasra Saidi Juma')
            ->assertSee('Benjamin William Mkapa High School')
            ->assertSee($this->certificate->certificate_number)
            ->assertDontSee('Private Parent')
            ->assertDontSee('+255 700 999 888')
            ->assertDontSee('14/03/2008');

        $this->assertDatabaseHas('verification_logs', ['document_type' => 'certificate', 'result' => 'valid', 'document_id' => $this->certificate->id]);
    }

    public function test_valid_id_card_and_lookup_by_number(): void
    {
        $this->get(route('verify.id', $this->card->verification_code))->assertOk()->assertSee('Valid ID card')->assertSee('BWM/2026/0001');

        $this->post(route('verify.lookup'), ['type' => 'certificate', 'number' => 'BWM/CERT/2026/00001'])
            ->assertOk()->assertSee('Valid certificate');
    }

    public function test_revoked_and_unknown_documents_are_not_valid(): void
    {
        Certificate::withoutGlobalScopes()->whereKey($this->certificate->id)->update(['status' => 'revoked']);

        $this->get(route('verify.certificate', $this->certificate->verification_code))->assertOk()->assertSee('Not valid')->assertDontSee('Valid certificate');
        $this->get(route('verify.certificate', 'DoesNotExist1234'))->assertOk()->assertSee('Not found');
        $this->get(route('verify.id', 'abc<script>'))->assertNotFound();

        $this->assertSame(1, VerificationLog::where('result', 'invalid')->count());
        $this->assertSame(1, VerificationLog::where('result', 'not_found')->count());
    }

    public function test_verification_is_rate_limited(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get(route('verify.certificate', 'Unknown'.$i.'xyz'))->assertOk();
        }

        $this->get(route('verify.certificate', 'OneTooManyxyz'))->assertStatus(429);
    }

    public function test_verification_is_public_even_for_logged_in_users_of_other_schools(): void
    {
        $other = $this->school('BNG');
        $this->actingAs($this->userFor($other))
            ->get(route('verify.certificate', $this->certificate->verification_code))->assertOk()->assertSee('Valid certificate');
    }
}
