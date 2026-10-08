<?php

namespace App\Services\Documents;

/**
 * Ready-made designs offered when creating a template. Colors use the
 * "@primary"/"@secondary" tokens so every preset follows school branding.
 */
class DesignPresets
{
    /** @return array<string, array{label: string, kind: string, type?: string, width: float, height: float, orientation: string, has_back?: bool, paper?: string, design: array<string, mixed>}> */
    public static function all(): array
    {
        return [
            'student_id_classic' => [
                'label' => 'Student ID – classic landscape (CR80)',
                'kind' => 'id_card', 'type' => 'STUDENT_ID',
                'width' => 85.6, 'height' => 53.98, 'orientation' => 'landscape', 'has_back' => true,
                'design' => self::studentIdClassic(),
            ],
            'staff_id_portrait' => [
                'label' => 'Staff ID – portrait (CR80)',
                'kind' => 'id_card', 'type' => 'STAFF_ID',
                'width' => 53.98, 'height' => 85.6, 'orientation' => 'portrait', 'has_back' => true,
                'design' => self::staffIdPortrait(),
            ],
            'photo_header_alevel' => [
                'label' => 'Photo header – A-Level (combination) (CR80)',
                'kind' => 'id_card', 'type' => 'STUDENT_ID',
                'width' => 85.6, 'height' => 53.98, 'orientation' => 'landscape', 'has_back' => true,
                'design' => self::photoHeader('alevel'),
            ],
            'photo_header_olevel' => [
                'label' => 'Photo header – O-Level (class) (CR80)',
                'kind' => 'id_card', 'type' => 'STUDENT_ID',
                'width' => 85.6, 'height' => 53.98, 'orientation' => 'landscape', 'has_back' => true,
                'design' => self::photoHeader('olevel'),
            ],
            'blank_card' => [
                'label' => 'Blank card (CR80 landscape)',
                'kind' => 'id_card', 'type' => 'STUDENT_ID',
                'width' => 85.6, 'height' => 53.98, 'orientation' => 'landscape', 'has_back' => false,
                'design' => ['front' => ['background' => ['color' => '#ffffff'], 'elements' => []], 'back' => ['background' => ['color' => '#ffffff'], 'elements' => []]],
            ],
            'certificate_completion' => [
                'label' => 'Certificate of Completion – A4 landscape',
                'kind' => 'certificate', 'paper' => 'A4',
                'width' => 297, 'height' => 210, 'orientation' => 'landscape',
                'design' => self::certificateLandscape(),
            ],
            'certificate_merit' => [
                'label' => 'Certificate of Merit – A4 portrait',
                'kind' => 'certificate', 'paper' => 'A4',
                'width' => 210, 'height' => 297, 'orientation' => 'portrait',
                'design' => self::certificatePortrait(),
            ],
            'blank_certificate' => [
                'label' => 'Blank certificate – A4 landscape',
                'kind' => 'certificate', 'paper' => 'A4',
                'width' => 297, 'height' => 210, 'orientation' => 'landscape',
                'design' => ['front' => ['background' => ['color' => '#ffffff'], 'elements' => []]],
            ],
        ];
    }

    /** @return array<string, string> */
    public static function optionsFor(string $kind): array
    {
        return collect(self::all())->where('kind', $kind)->map(fn ($p) => $p['label'])->all();
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    private static int $seq = 0;

    private static function el(string $type, float $x, float $y, float $w, float $h, array $props = []): array
    {
        return ['id' => 'el'.(++self::$seq), 'type' => $type, 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'rotation' => 0, 'z' => self::$seq] + $props;
    }

    private static function text(float $x, float $y, float $w, float $h, string $content, float $size, array $props = []): array
    {
        // $props first: array union keeps the left-hand value, so overrides must win over defaults.
        return self::el('text', $x, $y, $w, $h, $props + ['content' => $content, 'fontFamily' => 'helvetica', 'fontSize' => $size,
            'fontWeight' => 'normal', 'italic' => false, 'color' => '#111111', 'align' => 'left', 'lineHeight' => 1.2]);
    }

    private static function studentIdClassic(): array
    {
        self::$seq = 0;
        $white = ['color' => '#ffffff', 'align' => 'center'];

        return [
            'front' => ['background' => ['color' => '#ffffff'], 'elements' => [
                self::el('rect', 0, 0, 85.6, 12.5, ['fill' => '@primary', 'borderWidth' => 0]),
                self::el('image', 2, 1.5, 9.5, 9.5, ['source' => 'school_logo', 'fit' => 'contain']),
                self::text(12, 1.6, 71.6, 5.5, '{school_name}', 7.5, $white + ['fontWeight' => 'bold', 'uppercase' => true]),
                self::text(12, 7.4, 71.6, 4, 'STUDENT IDENTITY CARD', 5.5, $white + ['letterSpacing' => 1]),
                self::el('image', 3, 15, 20, 25, ['source' => 'photo', 'fit' => 'cover', 'radius' => 1, 'borderWidth' => 0.4, 'borderColor' => '@primary']),
                self::text(26, 14.8, 57, 5, '{full_name}', 8.5, ['fontWeight' => 'bold', 'uppercase' => true, 'color' => '@primary']),
                self::text(26, 20.5, 43, 19, "Adm No: {admission_number}\nClass: {class_stream}\nGender: {gender}\nYear: {academic_year}", 6.5, ['lineHeight' => 1.45]),
                self::el('qr', 71, 26, 12, 12, ['content' => '{verification_url}', 'color' => '#000000']),
                self::text(3, 42.3, 38, 3.5, 'ID: {card_number}', 6, ['fontWeight' => 'bold']),
                self::el('image', 46, 39.5, 18, 7, ['source' => 'principal_signature', 'fit' => 'contain']),
                self::text(46, 46.3, 18, 3, 'Principal', 4.5, ['align' => 'center', 'color' => '#444444']),
                self::el('rect', 0, 50, 85.6, 3.98, ['fill' => '@secondary', 'borderWidth' => 0]),
            ]],
            'back' => ['background' => ['color' => '#ffffff'], 'elements' => [
                self::el('rect', 0, 0, 85.6, 4, ['fill' => '@primary', 'borderWidth' => 0]),
                self::text(5, 7, 75.6, 4, 'IF FOUND, PLEASE RETURN TO:', 7, ['align' => 'center', 'fontWeight' => 'bold']),
                self::text(5, 11.5, 75.6, 14, "{school_name}\n{school_address}\nTel: {school_phone}  ·  {school_email}", 6.5, ['align' => 'center', 'lineHeight' => 1.35]),
                self::text(5, 26, 58, 8, 'This card is school property and must be carried at all times. Valid until {expiry_date}.', 5.5, ['align' => 'center', 'italic' => true, 'color' => '#444444']),
                self::el('image', 66, 22, 15, 15, ['source' => 'school_stamp', 'fit' => 'contain', 'opacity' => 0.85]),
                self::el('barcode', 17.8, 36, 50, 9, ['content' => '{card_number}', 'color' => '#000000']),
                self::text(17.8, 45.5, 50, 3.5, '{card_number}', 6, ['align' => 'center']),
                self::el('rect', 0, 50, 85.6, 3.98, ['fill' => '@primary', 'borderWidth' => 0]),
            ]],
        ];
    }

    /**
     * Landscape student ID with a campus photo header in a framed band, round photo,
     * centered auto-shrinking name and a four-column details row — the Benjamin William
     * Mkapa High School card (systems/benja/templates/id-card*.php) mapped to CR80 mm.
     *
     * A-Level shows the subject combination and uses the primary color for accents;
     * O-Level shows the class and uses the secondary color. The header photo and
     * watermark are template assets (templates/...); without them plain fills are used.
     */
    public static function photoHeader(string $variant, ?string $headerImage = null, ?string $watermark = null): array
    {
        self::$seq = 0;
        $accent = $variant === 'olevel' ? '@secondary' : '@primary';
        $white = ['fontWeight' => 'bold', 'color' => '#ffffff', 'align' => 'center', 'lineHeight' => 1.15];
        $label = ['fontWeight' => 'bold', 'color' => $accent, 'uppercase' => true, 'lineHeight' => 1.15];
        $value = ['fontWeight' => 'bold', 'color' => '#222222', 'lineHeight' => 1.15, 'shrink' => true, 'minFontSize' => 3.5];
        $divider = ['fill' => '#000000', 'borderWidth' => 0];

        // A-Level: combination; O-Level: class.
        [$firstLabel, $firstValue] = $variant === 'olevel' ? ['Class:', '{class_stream}'] : ['Combination:', '{combination}'];

        $front = [
            // Body watermark (chalkboard formulas at 10% baked into the image)
            $watermark
                ? self::el('image', 0, 21.3, 85.6, 32.68, ['source' => 'custom', 'src' => $watermark, 'fit' => 'cover'])
                : self::el('rect', 0, 21.3, 85.6, 32.68, ['fill' => '#ececec', 'borderWidth' => 0]),
            // Framed photo header
            self::el('rect', 0, 0, 85.6, 21.3, ['fill' => '@primary', 'borderWidth' => 0]),
            $headerImage
                ? self::el('image', 1.76, 1.93, 82.08, 19.37, ['source' => 'custom', 'src' => $headerImage, 'fit' => 'cover'])
                : self::el('rect', 1.76, 1.93, 82.08, 19.37, ['fill' => '#5b8f99', 'borderWidth' => 0]),
            self::text(33, 5.9, 49, 2.6, "President's Office", 5.8, $white),
            self::text(33, 8.3, 49, 2.6, 'Regional Administration and Local Government', 5.4, $white + ['shrink' => true, 'minFontSize' => 4]),
            self::text(33, 10.7, 49, 2.9, '{school_name}.', 6.4, $white + ['uppercase' => true, 'shrink' => true, 'minFontSize' => 4]),
            // Round photo overlapping the header (silhouette when no photo)
            self::el('image', 5.15, 5.9, 25, 25, ['source' => 'photo', 'fit' => 'cover', 'radius' => 12.5, 'borderWidth' => 0.9, 'borderColor' => '#ffffff']),
            // Name auto-shrinks (28px -> 14px in the original) + initials signature
            self::text(31.5, 24.7, 50.4, 4.2, '{full_name}', 9.1, ['fontWeight' => 'bold', 'color' => $accent, 'align' => 'center', 'lineHeight' => 1.2, 'shrink' => true, 'minFontSize' => 4.5]),
            self::text(41, 28.7, 28, 3.6, '{name_initials}', 8.5, ['fontFamily' => 'times', 'italic' => true, 'color' => '#333333', 'align' => 'center', 'shrink' => true, 'minFontSize' => 5]),
            self::el('image', 72.5, 31.6, 6.6, 6.6, ['source' => 'school_logo', 'fit' => 'contain']),
            // Details row: four columns separated by black bars
            self::el('rect', 3.45, 36.5, 0.55, 14, $divider),
            self::text(6.4, 37.6, 15.8, 2.4, $firstLabel, 4.85, $label),
            // O-Level stacks the class: "Form" on top, "I - IV" below.
            $variant === 'olevel'
                ? self::text(6.4, 39.6, 15.8, 4.4, '{class_stacked}', 5.8, ['lineHeight' => 1.0] + $value)
                : self::text(6.4, 40.2, 15.8, 2.6, $firstValue, 5.8, $value),
            self::text(6.4, 44.5, 15.8, 2.4, 'Year', 4.85, $label),
            self::text(6.4, 47.1, 15.8, 2.6, '{year_range}', 5.8, $value),
            self::el('rect', 22.8, 36.5, 0.55, 14, $divider),
            self::text(25.8, 37.6, 12.5, 2.4, 'Gender', 4.85, $label),
            self::text(25.8, 40.2, 12.5, 2.6, '{gender}', 5.8, $value),
            self::text(25.8, 44.5, 12.5, 2.4, 'Admin No.', 4.85, $label),
            self::text(25.8, 47.1, 12.5, 2.6, '{admission_number}', 5.8, $value),
            self::el('rect', 38.7, 36.5, 0.55, 14, $divider),
            self::text(42.7, 41.4, 23.3, 2.5, '{school_phone}', 5.2, $value),
            self::text(42.7, 44.4, 23.3, 2.5, '{school_email}', 5.2, $value),
            self::text(42.7, 47.4, 23.3, 2.5, '{school_website}', 5.2, ['fontWeight' => 'bold', 'color' => $accent, 'shrink' => true, 'minFontSize' => 3.5]),
            self::el('rect', 66.6, 36.5, 0.55, 14, $divider),
            self::el('image', 67.6, 39.3, 13.8, 6.2, ['source' => 'principal_signature', 'fit' => 'contain']),
            self::el('line', 68.3, 45.8, 11.8, 0.15, ['color' => '#222222', 'thickness' => 0.15]),
            self::text(67.4, 46.9, 13.6, 2.4, 'Headmaster', 4.5, ['fontWeight' => 'bold', 'uppercase' => true, 'align' => 'center', 'letterSpacing' => 0.3]),
            self::text(0, 51.5, 85.6, 2.3, "Student's Identification Card", 4.85, ['fontWeight' => 'bold', 'color' => '#666666', 'uppercase' => true, 'align' => 'center', 'letterSpacing' => 1]),
        ];

        $back = [
            self::el('rect', 0, 0, 85.6, 3, ['fill' => '@primary', 'borderWidth' => 0]),
            self::el('image', 3.5, 5.5, 11, 9, ['source' => 'school_logo', 'fit' => 'contain']),
            self::text(16, 6, 66, 4, '{school_name}', 7.5, ['fontFamily' => 'times', 'fontWeight' => 'bold', 'uppercase' => true, 'color' => '@primary']),
            self::text(16, 10.2, 66, 5.4, "{school_address}  ·  Tel: {school_phone}\n{school_email}  ·  {school_website}", 5.3, ['lineHeight' => 1.3, 'color' => '#333333']),
            self::el('line', 3.5, 16.4, 78.6, 0.3, ['color' => $accent, 'thickness' => 0.3]),
            self::text(3.5, 18, 54, 16, "This card is the property of the school and is not transferable. It must be carried at all times while on school premises.\n\nIf found, please return it to the school address above.", 5.5, ['lineHeight' => 1.3, 'color' => '#222222']),
            self::el('qr', 63, 18, 19, 19, ['content' => '{verification_url}', 'color' => '#000000']),
            self::text(59, 37.3, 27, 2.6, 'Scan to verify', 5, ['align' => 'center', 'color' => '#555555']),
            self::text(3.5, 35.5, 54, 2.8, 'Card No: {card_number}', 6, ['fontWeight' => 'bold']),
            self::text(3.5, 38.6, 54, 2.8, 'Valid until: {expiry_date}', 6, ['fontWeight' => 'bold', 'color' => $accent]),
            self::el('barcode', 3.5, 42.5, 50, 7, ['content' => '{card_number}', 'color' => '#000000']),
            self::el('image', 62, 40, 22, 8, ['source' => 'principal_signature', 'fit' => 'contain']),
            self::text(59, 48, 27, 2.4, 'Headmaster', 5.2, ['fontWeight' => 'bold', 'uppercase' => true, 'align' => 'center']),
            self::el('rect', 0, 51, 85.6, 2.98, ['fill' => '@primary', 'borderWidth' => 0]),
        ];

        return [
            'front' => ['background' => ['color' => '#ffffff', 'image' => null], 'elements' => $front],
            'back' => ['background' => ['color' => '#ffffff', 'image' => null], 'elements' => $back],
        ];
    }

    /**
     * A side that is just a finished artwork image (e.g. a school's own printed card back),
     * stretched over the whole card. Elements can still be added on top in the designer.
     *
     * @return array{background: array{color: string, image: string}, elements: array<int, mixed>}
     */
    public static function imageSide(string $image): array
    {
        return ['background' => ['color' => '#ffffff', 'image' => $image], 'elements' => []];
    }

    private static function staffIdPortrait(): array
    {
        self::$seq = 0;
        $center = ['align' => 'center'];

        return [
            'front' => ['background' => ['color' => '#ffffff'], 'elements' => [
                self::el('rect', 0, 0, 53.98, 20, ['fill' => '@primary', 'borderWidth' => 0]),
                self::el('image', 22, 1.5, 10, 10, ['source' => 'school_logo', 'fit' => 'contain']),
                self::text(2, 12, 49.98, 7.5, '{school_name}', 6.5, $center + ['color' => '#ffffff', 'fontWeight' => 'bold', 'uppercase' => true, 'lineHeight' => 1.1]),
                self::el('rect', 0, 20, 53.98, 5, ['fill' => '@secondary', 'borderWidth' => 0]),
                self::text(0, 20.8, 53.98, 4, 'STAFF IDENTITY CARD', 6.5, $center + ['color' => '#ffffff', 'fontWeight' => 'bold', 'letterSpacing' => 1]),
                self::el('image', 14.5, 28, 25, 30, ['source' => 'photo', 'fit' => 'cover', 'radius' => 1, 'borderWidth' => 0.5, 'borderColor' => '@primary']),
                self::text(2, 60, 49.98, 5, '{full_name}', 8.5, $center + ['fontWeight' => 'bold', 'uppercase' => true]),
                self::text(2, 65.3, 49.98, 4, '{job_title}', 7, $center + ['color' => '@primary', 'fontWeight' => 'bold']),
                self::text(2, 69.5, 36, 8, "Dept: {department}\nNo: {employee_number}", 6, ['lineHeight' => 1.35]),
                self::el('qr', 40, 70, 11.5, 11.5, ['content' => '{verification_url}', 'color' => '#000000']),
                self::text(2, 78.5, 36, 4, 'ID: {card_number}', 5.5, ['fontWeight' => 'bold']),
                self::el('rect', 0, 84.1, 53.98, 1.5, ['fill' => '@primary', 'borderWidth' => 0]),
            ]],
            'back' => ['background' => ['color' => '#ffffff'], 'elements' => [
                self::el('rect', 0, 0, 53.98, 3, ['fill' => '@primary', 'borderWidth' => 0]),
                self::text(3, 6, 47.98, 4, 'IF FOUND, PLEASE RETURN TO', 6.5, $center + ['fontWeight' => 'bold']),
                self::text(3, 11, 47.98, 17, "{school_name}\n{school_address}\nTel: {school_phone}\n{school_email}", 6, $center + ['lineHeight' => 1.35]),
                self::el('image', 33, 28, 16, 16, ['source' => 'school_stamp', 'fit' => 'contain', 'opacity' => 0.85]),
                self::el('image', 8, 36, 28, 10, ['source' => 'principal_signature', 'fit' => 'contain']),
                self::el('line', 8, 46.5, 28, 0.3, ['color' => '#333333', 'thickness' => 0.25]),
                self::text(3, 47.5, 38, 7, "{principal_name}\nHead of School", 5.5, ['align' => 'center', 'lineHeight' => 1.3]),
                self::el('barcode', 7, 60, 40, 9, ['content' => '{card_number}', 'color' => '#000000']),
                self::text(7, 69.5, 40, 3.5, '{card_number}', 6, $center),
                self::text(3, 76, 47.98, 4, 'Valid until {expiry_date}', 6, $center + ['italic' => true]),
                self::el('rect', 0, 82.6, 53.98, 3, ['fill' => '@primary', 'borderWidth' => 0]),
            ]],
        ];
    }

    private static function certificateLandscape(): array
    {
        self::$seq = 0;
        $serif = ['fontFamily' => 'times', 'align' => 'center'];

        return ['front' => ['background' => ['color' => '#fffdf7'], 'elements' => [
            self::el('rect', 8, 8, 281, 194, ['fill' => 'transparent', 'borderWidth' => 2.2, 'borderColor' => '@primary']),
            self::el('rect', 12, 12, 273, 186, ['fill' => 'transparent', 'borderWidth' => 0.6, 'borderColor' => '@secondary']),
            self::el('image', 133.5, 19, 30, 30, ['source' => 'school_logo', 'fit' => 'contain']),
            self::text(20, 51, 257, 10, '{school_name}', 20, $serif + ['fontWeight' => 'bold', 'uppercase' => true, 'color' => '@primary']),
            self::text(20, 61.5, 257, 6, '{school_address}', 10, $serif + ['color' => '#555555']),
            self::text(20, 72, 257, 16, '{certificate_title}', 32, $serif + ['fontWeight' => 'bold', 'color' => '@secondary']),
            self::text(20, 93, 257, 8, 'This is to certify that', 13, $serif + ['italic' => true, 'color' => '#333333']),
            self::text(20, 103, 257, 14, '{full_name}', 28, $serif + ['fontWeight' => 'bold']),
            self::el('line', 78.5, 118.5, 140, 0.4, ['color' => '@primary', 'thickness' => 0.4]),
            self::text(40, 122, 217, 15, '{description}', 12.5, $serif + ['lineHeight' => 1.4]),
            self::text(40, 137.5, 217, 9, '{program}', 16, $serif + ['fontWeight' => 'bold', 'color' => '@primary']),
            self::text(40, 147.5, 217, 6, 'Academic Year {academic_year}', 11, $serif),
            self::el('image', 40, 157, 60, 19, ['source' => 'principal_signature', 'fit' => 'contain']),
            self::el('line', 40, 177, 60, 0.3, ['color' => '#333333', 'thickness' => 0.3]),
            self::text(40, 178.5, 60, 10, "{principal_name}\nHead of School", 10, $serif + ['lineHeight' => 1.3]),
            self::el('image', 128.5, 154, 40, 40, ['source' => 'school_stamp', 'fit' => 'contain', 'opacity' => 0.9]),
            self::el('qr', 222, 155, 26, 26, ['content' => '{verification_url}', 'color' => '#000000']),
            self::text(215, 181.5, 40, 4, 'Scan to verify', 7, ['align' => 'center', 'color' => '#555555']),
            self::text(20, 190, 90, 5, 'Date of issue: {issue_date}', 9, ['fontFamily' => 'times', 'color' => '#333333']),
            self::text(187, 190, 90, 5, 'Certificate No: {certificate_number}', 9, ['fontFamily' => 'times', 'align' => 'right', 'color' => '#333333']),
        ]]];
    }

    private static function certificatePortrait(): array
    {
        self::$seq = 0;
        $serif = ['fontFamily' => 'times', 'align' => 'center'];

        return ['front' => ['background' => ['color' => '#ffffff'], 'elements' => [
            self::el('rect', 6, 6, 198, 285, ['fill' => 'transparent', 'borderWidth' => 1.5, 'borderColor' => '@primary']),
            self::el('rect', 10, 10, 190, 277, ['fill' => 'transparent', 'borderWidth' => 0.5, 'borderColor' => '@secondary']),
            self::el('image', 85, 22, 40, 40, ['source' => 'school_logo', 'fit' => 'contain']),
            self::text(15, 66, 180, 10, '{school_name}', 18, $serif + ['fontWeight' => 'bold', 'uppercase' => true, 'color' => '@primary']),
            self::text(15, 77, 180, 6, '{school_address}', 9.5, $serif + ['color' => '#555555']),
            self::text(15, 95, 180, 16, '{certificate_title}', 30, $serif + ['fontWeight' => 'bold', 'color' => '@secondary']),
            self::text(15, 118, 180, 8, 'is proudly presented to', 13, $serif + ['italic' => true]),
            self::text(15, 130, 180, 14, '{full_name}', 26, $serif + ['fontWeight' => 'bold']),
            self::el('line', 45, 146, 120, 0.4, ['color' => '@primary', 'thickness' => 0.4]),
            self::text(25, 152, 160, 24, '{description}', 12.5, $serif + ['lineHeight' => 1.45]),
            self::text(25, 178, 160, 9, '{program}', 15, $serif + ['fontWeight' => 'bold', 'color' => '@primary']),
            self::text(25, 190, 160, 6, 'Academic Year {academic_year}  ·  Issued {issue_date}', 10.5, $serif),
            self::el('image', 25, 215, 60, 18, ['source' => 'principal_signature', 'fit' => 'contain']),
            self::el('line', 25, 234, 60, 0.3, ['color' => '#333333', 'thickness' => 0.3]),
            self::text(25, 235.5, 60, 10, "{principal_name}\nHead of School", 10, $serif + ['lineHeight' => 1.3]),
            self::el('image', 92, 212, 36, 36, ['source' => 'school_stamp', 'fit' => 'contain', 'opacity' => 0.9]),
            self::el('qr', 150, 210, 28, 28, ['content' => '{verification_url}', 'color' => '#000000']),
            self::text(140, 239, 48, 4, 'Scan to verify', 7, ['align' => 'center', 'color' => '#555555']),
            self::text(15, 272, 180, 5, 'Certificate No: {certificate_number}', 9, $serif + ['color' => '#333333']),
        ]]];
    }
}
