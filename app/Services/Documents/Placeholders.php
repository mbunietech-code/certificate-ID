<?php

namespace App\Services\Documents;

/**
 * The {placeholders} a template designer can insert into text and barcode
 * elements, grouped per document kind, with sample values for previews.
 */
class Placeholders
{
    private const SCHOOL = [
        'school_name' => ['School name', 'Benjamin William Mkapa High School'],
        'school_short_name' => ['School short name', 'BWM High'],
        'school_code' => ['School code', 'BWM'],
        'school_address' => ['School address', 'P.O. Box 123, Dar es Salaam'],
        'school_phone' => ['School phone', '+255 700 000 000'],
        'school_email' => ['School email', 'info@school.ac.tz'],
        'school_website' => ['School website', 'www.school.ac.tz'],
        'school_region' => ['Region', 'Dar es Salaam'],
        'school_district' => ['District', 'Ilala'],
        'principal_name' => ['Principal name', 'Dr. A. Mwakyusa'],
        'academic_year' => ['Academic year', '2026'],
    ];

    private const PERSON = [
        'full_name' => ['Full name', 'Amina Juma Hassan'],
        'first_name' => ['First name', 'Amina'],
        'middle_name' => ['Middle name', 'Juma'],
        'last_name' => ['Last name', 'Hassan'],
        'name_initials' => ['Initials + surname (A.J. Hassan)', 'A.J. Hassan'],
        'gender' => ['Gender', 'Female'],
        'date_of_birth' => ['Date of birth', '14/03/2010'],
    ];

    private const STUDENT = [
        'admission_number' => ['Admission number', 'BWM/2026/0123'],
        'level' => ['Level (O-Level / A-Level)', 'A-Level'],
        'year_range' => ['Year range (entry - completion)', '2024 - 2026'],
        'entry_year' => ['Entry year', '2024'],
        'completion_year' => ['Completion year', '2026'],
        'class' => ['Class / Form', 'Form IV'],
        'stream' => ['Stream', 'A'],
        'class_stream' => ['Class + stream', 'Form IV A'],
        'class_stacked' => ['Class on two lines (Form / I - IV)', "Form\nI - IV"],
        'combination' => ['Combination', 'PCM'],
        'nationality' => ['Nationality', 'Tanzanian'],
        'parent_name' => ['Parent / guardian', 'Juma Hassan'],
        'parent_phone' => ['Parent phone', '+255 711 111 111'],
        'address' => ['Student address', 'Kariakoo, Dar es Salaam'],
    ];

    private const STAFF = [
        'employee_number' => ['Employee number', 'EMP-0042'],
        'job_title' => ['Job title', 'Senior Teacher'],
        'department' => ['Department', 'Science'],
        'phone' => ['Phone', '+255 722 222 222'],
        'email' => ['Email', 'teacher@school.ac.tz'],
    ];

    private const ID_CARD = [
        'card_number' => ['ID card number', 'BWM/2026/0001'],
        'issue_date' => ['Issue date', '01/10/2026'],
        'expiry_date' => ['Expiry date', '30/09/2027'],
        'verification_url' => ['Verification URL', 'https://example.com/verify/id/abc'],
    ];

    private const CERTIFICATE = [
        'certificate_title' => ['Certificate title', 'Certificate of Completion'],
        'certificate_number' => ['Certificate number', 'BWM/CERT/2026/00001'],
        'program' => ['Course / event / program', 'Advanced Level Secondary Education'],
        'description' => ['Description / body text', 'has successfully completed the prescribed course of study.'],
        'issue_date' => ['Issue date', '01/10/2026'],
        'verification_url' => ['Verification URL', 'https://example.com/verify/certificate/abc'],
    ];

    /** @return array<string, array<string, string>> group => [key => label] */
    public static function groupsFor(string $kind, string $holderType = 'student'): array
    {
        $groups = ['School' => self::SCHOOL, 'Person' => self::PERSON];
        if ($kind === 'certificate') {
            $groups['Student'] = self::STUDENT;
            $groups['Certificate'] = self::CERTIFICATE;
        } elseif ($holderType === 'staff') {
            $groups['Staff'] = self::STAFF;
            $groups['ID card'] = self::ID_CARD;
        } else {
            $groups['Student'] = self::STUDENT;
            $groups['ID card'] = self::ID_CARD;
        }

        return array_map(fn ($items) => array_map(fn ($pair) => $pair[0], $items), $groups);
    }

    /** @return array<string, string> key => sample value */
    public static function samples(string $kind, string $holderType = 'student'): array
    {
        $all = self::SCHOOL + self::PERSON + ($holderType === 'staff' ? self::STAFF : self::STUDENT)
            + ($kind === 'certificate' ? self::CERTIFICATE : self::ID_CARD);

        return array_map(fn ($pair) => $pair[1], $all);
    }
}
