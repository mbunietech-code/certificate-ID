<?php

namespace App\Services\Documents;

use App\Models\Certificate;
use App\Models\IdCard;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;

/**
 * The values a template is filled with for one document:
 *   text     placeholder => string
 *   images   source => public-disk path (school_logo, photo, principal_signature, school_stamp)
 *   qr       verification URL
 *   colors   palette for "@primary" / "@secondary" color tokens (school branding)
 */
final class DocumentData
{
    /**
     * @param  array<string, string>  $text
     * @param  array<string, string|null>  $images
     */
    public function __construct(
        public array $text,
        public array $images,
        public string $qr,
        public array $colors = [],
    ) {}

    public static function forIdCard(IdCard $card): self
    {
        $holder = $card->holder;
        $school = $card->school;
        $text = self::schoolText($school, $card->academicYear?->name) + self::personText($holder) + [
            'card_number' => $card->card_number,
            'issue_date' => format_date($card->issued_at),
            'expiry_date' => format_date($card->expires_at),
            'verification_url' => $card->verificationUrl(),
        ];

        return new self($text, self::images($school, $holder), $card->verificationUrl(), self::colors($school));
    }

    public static function forCertificate(Certificate $certificate): self
    {
        $student = $certificate->student;
        $school = $certificate->school;
        $text = self::schoolText($school, $certificate->academicYear?->name)
            + ($student ? self::personText($student) : ['full_name' => $certificate->recipient_name]);

        $text = array_merge($text, [
            'full_name' => $certificate->recipient_name,
            'certificate_title' => $certificate->title,
            'certificate_number' => $certificate->certificate_number,
            'program' => (string) $certificate->program,
            'description' => (string) $certificate->description,
            'issue_date' => format_date($certificate->issued_on),
            'verification_url' => $certificate->verificationUrl(),
        ]);

        return new self($text, self::images($school, $student), $certificate->verificationUrl(), self::colors($school));
    }

    /** Sample data for designer previews; real school branding, real holder when one exists. */
    public static function sample(string $kind, string $holderType, ?School $school, Student|Staff|null $holder = null): self
    {
        $text = Placeholders::samples($kind, $holderType);
        if ($school) {
            $text = array_merge($text, self::schoolText($school, $school->currentAcademicYear?->name ?? $text['academic_year']));
        }
        if ($holder) {
            $text = array_merge($text, array_filter(self::personText($holder), fn ($v) => $v !== ''));
        }
        $url = verification_url($kind === 'certificate' ? 'certificate' : 'id', 'SAMPLE-PREVIEW');
        $text['verification_url'] = $url;

        return new self($text, $school ? self::images($school, $holder) : [], $url, $school ? self::colors($school) : []);
    }

    /** @return array<string, string> */
    private static function schoolText(School $school, ?string $academicYear): array
    {
        return [
            'school_name' => $school->name,
            'school_short_name' => (string) $school->displayName(),
            'school_code' => $school->school_code,
            'school_address' => (string) $school->address,
            'school_phone' => (string) $school->phone,
            'school_email' => (string) $school->email,
            'school_website' => (string) $school->website,
            'school_region' => (string) $school->region,
            'school_district' => (string) $school->district,
            'principal_name' => (string) $school->principal_name,
            'academic_year' => (string) $academicYear,
        ];
    }

    /** @return array<string, string> */
    private static function personText(Student|Staff|null $person): array
    {
        if (! $person) {
            return [];
        }

        $common = [
            'full_name' => $person->full_name,
            'first_name' => (string) $person->first_name,
            'middle_name' => (string) $person->middle_name,
            'last_name' => (string) $person->last_name,
            'name_initials' => self::initials($person),
            'gender' => ucfirst((string) $person->gender),
            'date_of_birth' => format_date($person->date_of_birth),
        ];

        if ($person instanceof Staff) {
            return $common + [
                'employee_number' => $person->employee_number,
                'job_title' => (string) $person->job_title,
                'department' => (string) $person->department,
                'phone' => (string) $person->phone,
                'email' => (string) $person->email,
            ];
        }

        return $common + [
            'admission_number' => $person->admission_number,
            'level' => (string) $person->level,
            'year_range' => $person->yearRange(),
            'entry_year' => (string) $person->entry_year,
            'completion_year' => (string) $person->completion_year,
            'class' => (string) $person->class_name,
            'stream' => (string) $person->stream,
            'class_stream' => $person->classLabel(),
            'combination' => (string) $person->combination,
            'nationality' => (string) $person->nationality,
            'parent_name' => (string) $person->parent_name,
            'parent_phone' => (string) $person->parent_phone,
            'address' => (string) $person->address,
        ];
    }

    /** "Nasra Saidi Juma" -> "N.S. Juma" (the signature-style line on ID cards). */
    private static function initials(Student|Staff $person): string
    {
        $letters = collect([$person->first_name, $person->middle_name])
            ->filter()->map(fn ($n) => mb_strtoupper(mb_substr(trim($n), 0, 1)).'.')->implode('');

        return trim($letters.' '.$person->last_name);
    }

    /** @return array<string, string> */
    private static function colors(School $school): array
    {
        return ['primary' => $school->primary_color, 'secondary' => $school->secondary_color];
    }

    /** @return array<string, string|null> */
    private static function images(School $school, Student|Staff|null $person): array
    {
        return [
            'school_logo' => $school->logo_path,
            'principal_signature' => $school->principal_signature_path,
            'school_stamp' => $school->school_stamp_path,
            'photo' => $person?->photo_path,
        ];
    }
}
