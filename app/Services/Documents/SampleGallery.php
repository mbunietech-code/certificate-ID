<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\Cache;

/**
 * Sample ID cards and certificates for the public landing page, rendered by
 * the real TemplateRenderer so visitors see exactly what the system prints.
 *
 * Everything here is fictional: invented schools and people, generic
 * emblems and illustrated avatars (see SampleAssets) — never real logos or
 * photos and never data from the database.
 */
class SampleGallery
{
    private const CACHE_KEY = 'landing:samples:v1';

    public function __construct(private TemplateRenderer $renderer, private SampleAssets $assets) {}

    /**
     * @return array<string, array{label: string, caption: string, kind: string, w: float, h: float, front: string, back: string|null}>
     */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), fn () => $this->build());
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, array<string, mixed>> */
    private function build(): array
    {
        $a = $this->assets->ensure();

        $schools = [
            'sample' => ['school_name' => 'Sample Secondary School', 'school_short_name' => 'Sample SS', 'school_code' => 'SSS',
                'school_address' => 'P.O. Box 100, Sample Town', 'school_phone' => '+255 700 000 100', 'school_email' => 'info@sample-school.test',
                'school_website' => 'www.sample-school.test', 'principal_name' => 'Mr. A. Mwakyusa', 'colors' => ['#1e3a8a', '#d97706'], 'emblem' => $a['emblems']['navy']],
            'demo' => ['school_name' => 'Demo High School', 'school_short_name' => 'Demo HS', 'school_code' => 'DHS',
                'school_address' => 'P.O. Box 200, Demo City', 'school_phone' => '0222 - 000 200', 'school_email' => 'office@demo-high.test',
                'school_website' => 'www.demo-high.test', 'principal_name' => 'Dr. N. Example', 'colors' => ['#0f766e', '#ca8a04'], 'emblem' => $a['emblems']['teal']],
            'example' => ['school_name' => 'Example Girls Secondary School', 'school_short_name' => 'Example GSS', 'school_code' => 'EGS',
                'school_address' => 'P.O. Box 300, Example Hills', 'school_phone' => '+255 700 000 300', 'school_email' => 'hello@example-girls.test',
                'school_website' => 'www.example-girls.test', 'principal_name' => 'Mrs. R. Sample', 'colors' => ['#7f1d1d', '#d97706'], 'emblem' => $a['emblems']['maroon']],
        ];

        $people = [
            'amina' => ['full_name' => 'Amina Juma Hassan', 'first_name' => 'Amina', 'last_name' => 'Hassan', 'name_initials' => 'A.J. Hassan',
                'gender' => 'Female', 'class' => 'Form IV', 'stream' => 'A', 'class_stream' => 'Form IV A', 'combination' => '',
                'admission_number' => 'SSS/2023/0142', 'year_range' => '2023 - 2026', 'photo' => $a['avatars'][1]],
            'baraka' => ['full_name' => 'Baraka Daudi Mwita', 'first_name' => 'Baraka', 'last_name' => 'Mwita', 'name_initials' => 'B.D. Mwita',
                'gender' => 'Male', 'class' => 'Form VI', 'stream' => '', 'class_stream' => 'Form VI', 'combination' => 'PCM',
                'admission_number' => 'DHS/2025/0318', 'year_range' => '2025 - 2027', 'photo' => $a['avatars'][0]],
            'neema' => ['full_name' => 'Neema Rose Komba', 'first_name' => 'Neema', 'last_name' => 'Komba', 'name_initials' => 'N.R. Komba',
                'gender' => 'Female', 'class' => 'Form II', 'stream' => 'B', 'class_stream' => 'Form II B', 'combination' => '',
                'admission_number' => 'EGS/2025/0076', 'year_range' => '2025 - 2028', 'photo' => $a['avatars'][3]],
            'daudi' => ['full_name' => 'Daudi Elia Kimaro', 'first_name' => 'Daudi', 'last_name' => 'Kimaro', 'name_initials' => 'D.E. Kimaro',
                'gender' => 'Male', 'job_title' => 'Senior Teacher', 'department' => 'Science', 'employee_number' => 'SSS-EMP-042', 'photo' => $a['avatars'][2]],
        ];

        $samples = [
            'student_classic' => ['Student ID · Classic', 'Two-sided CR80 PVC card', 'id_card', 'student_id_classic', null, 'sample', 'amina', 'SSS/2026/0142'],
            'student_alevel' => ['Student ID · Photo header (A-Level)', 'Combination + year range, name shrinks to fit', 'id_card', null, 'alevel', 'demo', 'baraka', 'DHS/2026/0318'],
            'student_olevel' => ['Student ID · Photo header (O-Level)', 'Same design, school colours switch automatically', 'id_card', null, 'olevel', 'example', 'neema', 'EGS/2026/0076'],
            'staff_portrait' => ['Staff ID · Portrait', 'Vertical card for teachers and staff', 'id_card', 'staff_id_portrait', null, 'sample', 'daudi', 'SSS/STF/2026/0042'],
            'certificate_completion' => ['Certificate of Completion', 'A4 landscape, numbered and QR-verifiable', 'certificate', 'certificate_completion', null, 'sample', 'amina', 'SSS/CERT/2026/00017'],
            'certificate_merit' => ['Certificate of Merit', 'A4 portrait with signature and stamp area', 'certificate', 'certificate_merit', null, 'demo', 'baraka', 'DHS/CERT/2026/00004'],
        ];

        $out = [];
        foreach ($samples as $key => [$label, $caption, $kind, $presetKey, $variant, $schoolKey, $personKey, $number]) {
            $preset = $presetKey ? DesignPresets::get($presetKey) : DesignPresets::get($variant === 'olevel' ? 'photo_header_olevel' : 'photo_header_alevel');
            $design = $presetKey ? $preset['design'] : DesignPresets::photoHeader($variant, $a['header'], $a['watermark']);
            $data = $this->data($kind, $schools[$schoolKey], $people[$personKey], $number, $a['signature']);
            $w = (float) $preset['width'];
            $h = (float) $preset['height'];

            $out[$key] = [
                'label' => $label,
                'caption' => $caption,
                'kind' => $kind,
                'w' => $w,
                'h' => $h,
                'front' => $this->renderer->renderSide($design['front'], $w, $h, $data, 'web'),
                'back' => isset($design['back']) && ($preset['has_back'] ?? false) ? $this->renderer->renderSide($design['back'], $w, $h, $data, 'web') : null,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $school
     * @param  array<string, mixed>  $person
     */
    private function data(string $kind, array $school, array $person, string $number, string $signature): DocumentData
    {
        $text = array_merge(
            Placeholders::samples($kind, isset($person['employee_number']) ? 'staff' : 'student'),
            array_diff_key($school, array_flip(['colors', 'emblem'])),
            array_diff_key($person, array_flip(['photo'])),
            [
                'academic_year' => '2026',
                'issue_date' => '05/01/2026',
                'expiry_date' => '31/12/2026',
                'card_number' => $number,
                'certificate_number' => $number,
                'certificate_title' => $kind === 'certificate' && str_contains($number, '00004') ? 'Certificate of Merit' : 'Certificate of Completion',
                'program' => str_contains($number, '00004') ? 'Best Student in Mathematics' : 'Certificate of Secondary Education',
                'description' => str_contains($number, '00004')
                    ? 'in recognition of outstanding academic performance and exemplary conduct throughout the year.'
                    : 'has successfully completed the prescribed four-year course of ordinary level secondary education.',
            ],
        );

        $url = verification_url($kind === 'certificate' ? 'certificate' : 'id', 'SAMPLE');
        $text['verification_url'] = $url;

        return new DocumentData(
            $text,
            ['school_logo' => $school['emblem'], 'photo' => $person['photo'], 'principal_signature' => $signature, 'school_stamp' => null],
            $url,
            ['primary' => $school['colors'][0], 'secondary' => $school['colors'][1]],
        );
    }
}
