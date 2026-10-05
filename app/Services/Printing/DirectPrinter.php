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

    public function print(PrintJob $job): void
    {
        $disk = Storage::disk(PrintJobService::PDF_DISK);
        if (! $job->file_path || ! $disk->exists($job->file_path)) {
            throw new RuntimeException('The PDF for this job is not available yet.');
        }

        if (PHP_OS_FAMILY !== 'Windows') {
            throw new RuntimeException('Direct printing is only available when this application is running on Windows.');
        }

        $printer = $this->printerName();
        $path = $disk->path($job->file_path);
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

            if ($defaultPrinter) {
                $this->setDefaultPrinter($defaultPrinter);
            }

            File::deleteDirectory($profile);
        }
    }

    private function setDefaultPrinter(string $printer): void
    {
        $process = new Process(['rundll32', 'printui.dll,PrintUIEntry', '/y', '/n', $printer]);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            $message = trim($process->getErrorOutput() ?: $process->getOutput());

            throw new RuntimeException($message ?: "Printer '{$printer}' was not found or could not be selected. Check the printer name in System settings.");
        }
    }

    private function defaultPrinterName(): ?string
    {
        $process = new Process(['cmd', '/c', 'wmic printer where default=true get name /value']);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        if (preg_match('/^Name=(.+)$/mi', $process->getOutput(), $matches)) {
            return trim($matches[1]);
        }

        return null;
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
