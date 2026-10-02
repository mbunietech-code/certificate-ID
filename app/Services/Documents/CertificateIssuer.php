<?php

namespace App\Services\Documents;

use App\Models\AcademicYear;
use App\Models\Certificate;
use App\Models\CertificateTemplate;
use App\Models\Student;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CertificateIssuer
{
    public function __construct(private NumberGenerator $numbers, private AuditLogger $audit) {}

    /**
     * @param  array{title: string, program?: string|null, description?: string|null, issued_on: string}  $details
     */
    public function issue(Student $student, CertificateTemplate $template, array $details, ?AcademicYear $year, ?int $userId = null): Certificate
    {
        if ($template->school_id !== null && $template->school_id !== $student->school_id) {
            throw new InvalidArgumentException('The template belongs to another school.');
        }

        return DB::transaction(function () use ($student, $template, $details, $year, $userId) {
            $school = $student->school;
            $format = $school->certificate_number_format ?: setting('default_certificate_number_format');
            $issuedOn = $details['issued_on'];

            $number = $this->numbers->next(
                $school,
                NumberGenerator::TYPE_CERTIFICATE,
                $format,
                $year?->name ?? substr($issuedOn, 0, 4),
                fn (string $candidate) => Certificate::withoutGlobalScopes()->where('certificate_number', $candidate)->exists(),
            );

            $certificate = new Certificate;
            $certificate->forceFill([
                'school_id' => $student->school_id,
                'student_id' => $student->id,
                'template_id' => $template->id,
                'academic_year_id' => $year?->id,
                'certificate_number' => $number,
                'verification_code' => IdCardIssuer::verificationCode(),
                'title' => $details['title'],
                'recipient_name' => $student->full_name,
                'program' => $details['program'] ?? null,
                'description' => $details['description'] ?? null,
                'issued_on' => $issuedOn,
                'status' => 'valid',
                'issued_by' => $userId,
            ])->save();

            $this->audit->log('certificate.generated', $certificate, "Issued certificate {$number} to {$student->full_name}", [], $student->school_id, $userId);

            return $certificate;
        });
    }
}
