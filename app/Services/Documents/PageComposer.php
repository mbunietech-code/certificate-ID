<?php

namespace App\Services\Documents;

use App\Models\CertificateTemplate;
use App\Models\IdCardTemplate;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Lays rendered document sides out on printable pages.
 *
 * ID card layouts:
 *   card  – one card side per page at the exact card size (PVC card printers, duplex: front, back, front, back…)
 *   sheet – many cards per A4/Letter sheet with optional crop marks; back sheets are column-mirrored
 *           so fronts and backs line up when printed duplex (long-edge flip).
 * Certificates: one certificate per page at the template's paper size.
 */
class PageComposer
{
    public const SHEET_SIZES = ['A4' => [210, 297], 'LETTER' => [215.9, 279.4]];

    public function __construct(private TemplateRenderer $renderer) {}

    /**
     * @param  array<int, DocumentData>  $documents
     * @param  array<string, mixed>  $options  layout, include_back, paper, margin, gap, crop_marks
     * @return array{width: float, height: float, pages: array<int, array<int, array{x: float, y: float, w: float, h: float, html: string, marks: bool}>>, labels: array<int, string>}
     */
    public function compose(IdCardTemplate|CertificateTemplate $template, array $documents, array $options, string $mode): array
    {
        $w = (float) $template->width_mm;
        $h = (float) $template->height_mm;
        $sides = $template->sides();
        $withBack = $template instanceof IdCardTemplate && $template->has_back && ($options['include_back'] ?? true);

        // PVC printers without duplex (e.g. Epson L8050 card tray) print all fronts, then all backs.
        $only = in_array($options['sides'] ?? 'both', ['front', 'back'], true) && $withBack ? $options['sides'] : null;
        if ($only === 'back') {
            $sides['front'] = $sides['back'];
        }
        if ($only !== null) {
            $withBack = false;
        }

        if ($template instanceof CertificateTemplate || ($options['layout'] ?? 'card') !== 'sheet') {
            $pages = [];
            $labels = [];
            $prefix = $template instanceof CertificateTemplate ? 'certificate' : 'card';
            foreach (array_values($documents) as $i => $data) {
                $pages[] = [$this->slot(0, 0, $w, $h, $this->renderer->renderSide($sides['front'], $w, $h, $data, $mode), false)];
                $labels[] = $prefix.'-'.($i + 1).($withBack ? '-front' : ($only ? "-{$only}" : ''));
                if ($withBack) {
                    $pages[] = [$this->slot(0, 0, $w, $h, $this->renderer->renderSide($sides['back'], $w, $h, $data, $mode), false)];
                    $labels[] = $prefix.'-'.($i + 1).'-back';
                }
            }

            return ['width' => $w, 'height' => $h, 'pages' => $pages, 'labels' => $labels];
        }

        if ($only !== 'back') {
            return $this->composeSheets($sides, $w, $h, $documents, $options, $withBack, $mode);
        }

        // Back sheets only: keep the column-mirrored back pages so they line up when the stack is flipped.
        $composed = $this->composeSheets($sides, $w, $h, $documents, $options, true, $mode);
        $keep = array_keys(array_filter($composed['labels'], fn (string $label) => str_ends_with($label, '-back')));
        $composed['pages'] = array_values(array_intersect_key($composed['pages'], array_flip($keep)));
        $composed['labels'] = array_values(array_intersect_key($composed['labels'], array_flip($keep)));

        return $composed;
    }

    private function composeSheets(array $sides, float $w, float $h, array $documents, array $options, bool $withBack, string $mode): array
    {
        [$pw, $ph] = self::SHEET_SIZES[$options['paper'] ?? 'A4'] ?? self::SHEET_SIZES['A4'];
        $margin = max(0, min(30, (float) ($options['margin'] ?? 10)));
        $gap = max(0, min(20, (float) ($options['gap'] ?? 4)));
        $marks = (bool) ($options['crop_marks'] ?? true);

        $cols = max(1, (int) floor(($pw - 2 * $margin + $gap) / ($w + $gap)));
        $rows = max(1, (int) floor(($ph - 2 * $margin + $gap) / ($h + $gap)));
        $perSheet = $cols * $rows;

        // Center the grid on the sheet.
        $gridW = $cols * $w + ($cols - 1) * $gap;
        $gridH = $rows * $h + ($rows - 1) * $gap;
        $offX = ($pw - $gridW) / 2;
        $offY = ($ph - $gridH) / 2;

        $pages = [];
        $labels = [];
        foreach (array_chunk($documents, $perSheet) as $sheet => $chunk) {
            $front = [];
            $back = [];
            foreach ($chunk as $i => $data) {
                $col = $i % $cols;
                $row = intdiv($i, $cols);
                $y = $offY + $row * ($h + $gap);
                $front[] = $this->slot($offX + $col * ($w + $gap), $y, $w, $h, $this->renderer->renderSide($sides['front'], $w, $h, $data, $mode), $marks);
                if ($withBack) {
                    $mirrored = $cols - 1 - $col;
                    $back[] = $this->slot($offX + $mirrored * ($w + $gap), $y, $w, $h, $this->renderer->renderSide($sides['back'], $w, $h, $data, $mode), $marks);
                }
            }
            $pages[] = $front;
            $labels[] = 'sheet-'.($sheet + 1).($withBack ? '-front' : '');
            if ($withBack) {
                $pages[] = $back;
                $labels[] = 'sheet-'.($sheet + 1).'-back';
            }
        }

        return ['width' => $pw, 'height' => $ph, 'pages' => $pages, 'labels' => $labels];
    }

    private function slot(float $x, float $y, float $w, float $h, string $html, bool $marks): array
    {
        return compact('x', 'y', 'w', 'h', 'html', 'marks');
    }

    /** Full HTML document for browser printing ($mode = web) or dompdf ($mode = pdf). */
    public function html(array $composed, string $title, string $mode, array $toolbar = []): string
    {
        return view('documents.sheet', [
            'composed' => $composed,
            'title' => $title,
            'mode' => $mode,
            'toolbar' => $toolbar,
        ])->render();
    }

    public function pdf(array $composed, string $title): string
    {
        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setChroot([storage_path('app/public'), public_path()]);
        $options->setDefaultFont('helvetica');
        $options->setDpi(300);
        $options->setTempDir($this->dir(storage_path('app/dompdf-tmp')));
        $options->setFontDir($this->dir(storage_path('fonts')));
        $options->setFontCache($this->dir(storage_path('fonts')));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($this->html($composed, $title, 'pdf'));
        $mmToPt = 72 / 25.4;
        $dompdf->setPaper([0, 0, $composed['width'] * $mmToPt, $composed['height'] * $mmToPt]);
        $dompdf->addInfo('Title', $title);
        $dompdf->render();

        return (string) $dompdf->output();
    }

    private function dir(string $path): string
    {
        if (! is_dir($path)) {
            mkdir($path, 0775, true);
        }

        return $path;
    }
}
