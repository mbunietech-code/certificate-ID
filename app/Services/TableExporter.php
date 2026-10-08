<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports tabular data. Rows are an iterable (use lazy()/cursor() for large
 * sets) so CSV streams without loading everything into memory. XLSX and PDF
 * are capped because they are built in memory.
 */
class TableExporter
{
    public const FORMATS = ['csv', 'xlsx', 'pdf'];

    public const MAX_XLSX_ROWS = 50000;

    public const MAX_PDF_ROWS = 3000;

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<int, array<int, mixed>>  $rows
     */
    public function download(string $format, string $filename, string $title, array $headers, iterable $rows): Response
    {
        return match ($format) {
            'xlsx' => $this->xlsx($filename, $title, $headers, $rows),
            'pdf' => $this->pdf($filename, $title, $headers, $rows),
            default => $this->csv($filename, $headers, $rows),
        };
    }

    private function csv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens accents correctly
            fputcsv($out, $headers, escape: '');
            foreach ($rows as $row) {
                fputcsv($out, array_map([$this, 'safeCell'], $row), escape: '');
            }
            fclose($out);
        }, "{$filename}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function xlsx(string $filename, string $title, array $headers, iterable $rows): StreamedResponse
    {
        $sheet = (new Spreadsheet)->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/\?\*\[\]:]/', ' ', $title), 0, 31));
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('1:1')->getFont()->setBold(true);

        $r = 2;
        foreach ($rows as $row) {
            if ($r > self::MAX_XLSX_ROWS + 1) {
                break;
            }
            $sheet->fromArray(array_map(fn ($v) => $this->safeCell($v), array_values($row)), null, 'A'.$r++);
        }
        foreach (range(1, count($headers)) as $col) {
            $sheet->getColumnDimensionByColumn($col)->setAutoSize(true);
        }

        $writer = new Xlsx($sheet->getParent());

        return response()->streamDownload(fn () => $writer->save('php://output'), "{$filename}.xlsx", [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    private function pdf(string $filename, string $title, array $headers, iterable $rows): Response
    {
        $limited = [];
        foreach ($rows as $row) {
            if (count($limited) >= self::MAX_PDF_ROWS) {
                break;
            }
            $limited[] = $row;
        }

        // A PDF page is narrow: leave out columns that are empty for every row (e.g. unused contact fields).
        $usedColumns = array_values(array_filter(array_keys($headers), fn (int $i) => $limited === []
            || collect($limited)->contains(fn (array $row) => ($row[$i] ?? null) !== null && $row[$i] !== '')));
        $headers = array_map(fn (int $i) => $headers[$i], $usedColumns);
        $limited = array_map(fn (array $row) => array_map(fn (int $i) => $row[$i] ?? null, $usedColumns), $limited);

        $html = view('exports.table-pdf', ['title' => $title, 'headers' => $headers, 'rows' => $limited,
            'truncated' => count($limited) >= self::MAX_PDF_ROWS])->render();

        $options = new Options;
        $options->setIsRemoteEnabled(false);
        $options->setDefaultFont('helvetica');
        $options->setTempDir(storage_path('app'));
        $options->setFontDir(storage_path('fonts'));
        $options->setFontCache(storage_path('fonts'));
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', count($headers) > 6 ? 'landscape' : 'portrait');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
        ]);
    }

    /** Neutralise spreadsheet formula injection (=, +, -, @ at the start of a cell). */
    public function safeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }
}
