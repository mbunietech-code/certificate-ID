<?php

namespace App\Services\Documents;

/**
 * Measures text width using the same AFM/UFM glyph metrics dompdf renders with,
 * so "shrink to fit" text sizes match the PDF exactly (and Arial in browsers closely).
 */
class TextMeasurer
{
    /** family => [regular, bold, italic, boldItalic] metric file basenames */
    private const FILES = [
        'helvetica' => ['Helvetica.afm', 'Helvetica-Bold.afm', 'Helvetica-Oblique.afm', 'Helvetica-BoldOblique.afm'],
        'times' => ['Times-Roman.afm', 'Times-Bold.afm', 'Times-Italic.afm', 'Times-BoldItalic.afm'],
        'courier' => ['Courier.afm', 'Courier-Bold.afm', 'Courier-Oblique.afm', 'Courier-BoldOblique.afm'],
        'dejavu sans' => ['DejaVuSans.ufm', 'DejaVuSans-Bold.ufm', 'DejaVuSans-Oblique.ufm', 'DejaVuSans-BoldOblique.ufm'],
        'dejavu serif' => ['DejaVuSerif.ufm', 'DejaVuSerif-Bold.ufm', 'DejaVuSerif-Italic.ufm', 'DejaVuSerif-BoldItalic.ufm'],
    ];

    /** @var array<string, array<int, int>> file => [codepoint => width/1000 em] */
    private static array $metrics = [];

    /** Width in mm of a single line of text. */
    public function widthMm(string $text, string $family, bool $bold, bool $italic, float $sizePt, float $letterSpacingPt = 0): float
    {
        $widths = $this->metricsFor($family, $bold, $italic);
        $units = 0;
        $chars = mb_str_split($text);
        foreach ($chars as $char) {
            $units += $widths[mb_ord($char)] ?? $widths[63] ?? 556; // unknown glyph ≈ "?"
        }

        $pt = $units / 1000 * $sizePt + max(0, count($chars) - 1) * $letterSpacingPt;

        return $pt * 25.4 / 72;
    }

    /**
     * Largest font size (≤ $sizePt, ≥ $minPt) at which every line fits in $maxWidthMm.
     *
     * @param  array<int, string>  $lines
     */
    public function fitSize(array $lines, float $maxWidthMm, string $family, bool $bold, bool $italic, float $sizePt, float $minPt, float $letterSpacingPt = 0): float
    {
        $widest = 0.0;
        foreach ($lines as $line) {
            $widest = max($widest, $this->widthMm($line, $family, $bold, $italic, $sizePt, $letterSpacingPt));
        }

        if ($widest <= $maxWidthMm * 0.98 || $widest <= 0) {
            return $sizePt;
        }

        return max($minPt, floor($sizePt * ($maxWidthMm * 0.97) / $widest * 10) / 10);
    }

    /** @return array<int, int> */
    private function metricsFor(string $family, bool $bold, bool $italic): array
    {
        $files = self::FILES[$family] ?? self::FILES['helvetica'];
        $file = $files[($bold ? 1 : 0) + ($italic ? 2 : 0)];

        if (! isset(self::$metrics[$file])) {
            $path = base_path('vendor/dompdf/dompdf/lib/fonts/'.$file);
            $widths = [];
            if (is_file($path)) {
                $isUnicode = str_ends_with($file, '.ufm');
                foreach (file($path, FILE_IGNORE_NEW_LINES) as $line) {
                    if (preg_match($isUnicode ? '/^U (\d+) ; WX (\d+)/' : '/^C (\d+) ; WX (\d+)/', $line, $m) && (int) $m[1] >= 0) {
                        $widths[(int) $m[1]] = (int) $m[2];
                    }
                }
            }
            self::$metrics[$file] = $widths;
        }

        return self::$metrics[$file];
    }
}
