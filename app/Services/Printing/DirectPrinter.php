<?php

namespace App\Services\Printing;

use App\Models\PrintJob;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;

/** Sends generated PDFs to the Windows printer configured in system settings. */
class DirectPrinter
{
    public function printerName(): string
    {
        return (string) setting('direct_print_printer', 'EPSON L8050 Series');
    }

    /** @param  string  $sides  both | front | back — PVC card trays print all fronts, then all backs */
    public function print(PrintJob $job, string $sides = 'both'): void
    {
        if (! $job->file_path) {
            throw new RuntimeException('The PDF for this job is not available yet.');
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('Direct printing is only available when this application is running on Windows.');
        }

        $printer = $this->printerName();
        $path = Storage::disk(PrintJobService::PDF_DISK)->path(app(PrintJobService::class)->ensurePdf($job, $sides));
        $browser = $this->browserPath();
        $defaultPrinter = $this->defaultPrinterName();
        $profile = storage_path('app/direct-print/'.uniqid('', true));
        $process = null;

        try {
            $this->setDefaultPrinter($printer);
            File::ensureDirectoryExists($profile);

            $process = new Process([
                $browser,
                '--kiosk-printing',
                '--disable-restore-session-state',
                '--no-first-run',
                "--user-data-dir={$profile}",
                $this->fileUri($path),
            ]);
            $process->setTimeout(30);
            $process->start();
            sleep(12);
        } finally {
            if ($process instanceof Process && $process->isRunning()) {
                $process->stop(2);
            }

            if ($defaultPrinter && $defaultPrinter !== $printer) {
                try {
                    $this->setDefaultPrinter($defaultPrinter);
                } catch (RuntimeException $e) {
                    report($e); // The job already printed; restoring the old default is best effort.
                }
            }

            File::deleteDirectory($profile);
        }
    }

    /**
     * Make $printer the Windows default via WMI (Win32_Printer.SetDefaultPrinter).
     * Unlike "rundll32 printui.dll /y" this never opens a dialog, so it cannot hang
     * when "Let Windows manage my default printer" is on. The switch is verified:
     * if Windows still reports another default printer nothing is printed.
     */
    private function setDefaultPrinter(string $printer): void
    {
        $output = $this->powershell(
            '$p = Get-CimInstance Win32_Printer | Where-Object Name -eq $env:TARGET_PRINTER; '
            .'if (-not $p) { "NOT_FOUND"; exit 0 }; '
            .'$r = Invoke-CimMethod -InputObject $p -MethodName SetDefaultPrinter; '
            .'"RESULT=" + $r.ReturnValue',
            ['TARGET_PRINTER' => $printer],
        );

        if (str_contains($output, 'NOT_FOUND')) {
            throw new RuntimeException("Printer '{$printer}' was not found. Check the printer name in System settings (Direct printer).");
        }

        if ($this->defaultPrinterName() !== $printer) {
            throw new RuntimeException("Windows did not switch the default printer to '{$printer}', so nothing was printed. "
                .'Turn off "Let Windows manage my default printer" in Windows Settings → Printers & scanners, then try again.');
        }
    }

    private function defaultPrinterName(): ?string
    {
        $name = trim($this->powershell('(Get-CimInstance Win32_Printer -Filter "Default=True").Name'));

        return $name !== '' ? $name : null;
    }

    /** @param  array<string, string>  $env  passed as environment variables (no quoting/injection issues) */
    private function powershell(string $script, array $env = []): string
    {
        $process = new Process(['powershell', '-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', $script], null, $env);
        $process->setTimeout(30);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Windows printer settings could not be read: '.trim($process->getErrorOutput() ?: $process->getOutput()));
        }

        return $process->getOutput();
    }

    private function browserPath(): string
    {
        foreach ([
            'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
            'C:\Program Files\Google\Chrome\Application\chrome.exe',
            'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        throw new RuntimeException('Microsoft Edge or Google Chrome is required for direct PDF printing.');
    }

    private function fileUri(string $path): string
    {
        return 'file:///'.str_replace(['\\', ' '], ['/', '%20'], $path);
    }
}
