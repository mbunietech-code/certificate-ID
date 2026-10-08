<?php

namespace App\Http\Controllers;

use App\Models\PrintJob;
use App\Models\School;
use App\Models\User;
use App\Services\Documents\PageComposer;
use App\Services\Printing\DirectPrinter;
use App\Services\Printing\PrintJobService;
use App\Services\TableExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Print queue, job detail/progress, browser printing, PDF download and print history. */
class PrintCenterController extends Controller
{
    public const FILTERS = ['school_id', 'user_id', 'type', 'status', 'job_number', 'date_from', 'date_to'];

    public function index(): View
    {
        $this->authorize('viewAny', PrintJob::class);

        $active = PrintJob::with(['user:id,name', 'school:id,school_code'])
            ->whereIn('status', ['pending', 'processing'])->latest('id')->limit(50)->get();
        $recent = PrintJob::with(['user:id,name', 'school:id,school_code'])
            ->whereNotIn('status', ['pending', 'processing'])->latest('id')->limit(20)->get();

        return view('print.index', compact('active', 'recent'));
    }

    public function show(PrintJob $printJob, PrintJobService $service): View
    {
        $this->authorize('view', $printJob);

        $printJob->load(['user:id,name', 'school']);
        $failed = $printJob->items()->where('status', 'failed')->with('subject')->limit(100)->get();

        return view('print.show', [
            'job' => $printJob,
            'failed' => $failed,
            'template' => $printJob->template(),
            'twoSidedCards' => $printJob->status === 'completed' && $service->isTwoSided($printJob) && $printJob->option('layout', 'card') === 'card',
        ]);
    }

    /** Polled by the job page while it is processing. */
    public function status(PrintJob $printJob): JsonResponse
    {
        $this->authorize('view', $printJob);

        return response()->json([
            'status' => $printJob->status,
            'total' => $printJob->total_items,
            'completed' => $printJob->completed_items,
            'failed' => $printJob->failed_items,
            'percent' => $printJob->progressPercent(),
            'finished' => $printJob->isFinished(),
            'has_pdf' => (bool) $printJob->file_path,
            'error' => $printJob->error_message,
            'waiting' => $printJob->status === 'pending' && $printJob->created_at->lt(now()->subMinute()),
        ]);
    }

    /** HTML pages sized exactly to the card/paper for printing from the browser. */
    public function browserPrint(Request $request, PrintJob $printJob, PrintJobService $service): Response|RedirectResponse
    {
        $this->authorize('print', $printJob);

        if ($printJob->completed_items === 0) {
            return redirect()->route('print.show', $printJob)->with('error', 'This job has no generated documents yet.');
        }

        $sides = $this->sides($request, $printJob, $service);
        $composed = $service->compose($printJob, 'web', $sides);
        $service->markPrinted($printJob, 'browser');

        return response(app(PageComposer::class)->html($composed, "{$printJob->job_number} – {$printJob->template_name}".self::SIDE_TITLES[$sides], 'web', [
            ['label' => '← Back to job', 'url' => route('print.show', $printJob)],
            ...($printJob->file_path ? [['label' => 'Download PDF', 'url' => route('print.pdf', [$printJob, 'sides' => $sides])]] : []),
        ]));
    }

    public function download(Request $request, PrintJob $printJob, PrintJobService $service): SymfonyResponse
    {
        $this->authorize('print', $printJob);

        abort_unless($printJob->file_path && Storage::disk(PrintJobService::PDF_DISK)->exists($printJob->file_path), 404, 'The PDF for this job is not available yet.');

        $sides = $this->sides($request, $printJob, $service);
        $path = $service->ensurePdf($printJob, $sides);
        $service->markPrinted($printJob, 'pdf_download');

        return Storage::disk(PrintJobService::PDF_DISK)->download($path, basename($path), ['Content-Type' => 'application/pdf']);
    }

    public function directPrint(Request $request, PrintJob $printJob, DirectPrinter $printer, PrintJobService $service): RedirectResponse
    {
        $this->authorize('print', $printJob);

        if ($printJob->completed_items === 0) {
            return back()->with('error', 'This job has no generated documents yet.');
        }

        $sides = $this->sides($request, $printJob, $service);

        try {
            $printer->print($printJob, $sides);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $service->markPrinted($printJob, 'direct');

        return back()->with('success', match ($sides) {
            'front' => "Fronts sent to {$printer->printerName()}. When they are done, flip the cards (printed side down) in the same slots and print the backs.",
            'back' => "Backs sent to {$printer->printerName()}.",
            default => "Sent to {$printer->printerName()}.",
        });
    }

    private const SIDE_TITLES = ['both' => '', 'front' => ' – fronts', 'back' => ' – backs'];

    /** Requested side(s): only two-sided card jobs can be split into fronts / backs. */
    private function sides(Request $request, PrintJob $job, PrintJobService $service): string
    {
        $sides = (string) $request->input('sides', 'both');

        return in_array($sides, PrintJobService::SIDES, true) && $service->isTwoSided($job) ? $sides : 'both';
    }

    /** Re-run failed/stuck jobs (pending items are processed again; done items are kept). */
    public function retry(PrintJob $printJob, PrintJobService $service): RedirectResponse
    {
        $this->authorize('print', $printJob);
        abort_if($printJob->status === 'processing' && $printJob->started_at?->gt(now()->subMinutes(15)), 409, 'This job is still processing.');

        $printJob->items()->where('status', 'failed')->update(['status' => 'pending', 'error' => null]);
        $printJob->forceFill(['status' => 'pending', 'failed_items' => 0, 'error_message' => null, 'completed_at' => null])->save();
        $this->audit('print_job.retried', $printJob, "Retried print job {$printJob->job_number}");
        $service->dispatch($printJob);

        return back()->with('success', 'The job was queued again.');
    }

    public function cancel(PrintJob $printJob): RedirectResponse
    {
        $this->authorize('print', $printJob);
        abort_if($printJob->isFinished(), 409, 'This job has already finished.');

        $printJob->forceFill(['status' => 'cancelled', 'completed_at' => now()])->save();
        $this->audit('print_job.cancelled', $printJob, "Cancelled print job {$printJob->job_number}");

        return back()->with('success', 'Print job cancelled. Documents already issued remain valid.');
    }

    public function history(Request $request): View
    {
        $this->authorize('viewAny', PrintJob::class);

        $filters = $request->only(self::FILTERS);
        $jobs = PrintJob::query()->filter($filters)
            ->with(['user:id,name', 'school:id,school_code,name'])
            ->latest('id')
            ->paginate(30)->withQueryString();

        return view('print.history', [
            'jobs' => $jobs,
            'filters' => $filters,
            'users' => User::visibleTo($request->user(), $this->tenant()->scopedSchoolId())->orderBy('name')->get(['id', 'name']),
            'schools' => $request->user()->isSuperAdmin() ? School::orderBy('name')->get(['id', 'name']) : collect(),
        ]);
    }

    public function exportHistory(Request $request, TableExporter $exporter): SymfonyResponse
    {
        $this->authorize('viewAny', PrintJob::class);

        $format = in_array($request->query('format'), TableExporter::FORMATS, true) ? $request->query('format') : 'csv';
        $query = PrintJob::query()->filter($request->only(self::FILTERS))->with(['user:id,name', 'school:id,school_code'])->latest('id');
        $rows = (function () use ($query) {
            foreach ($query->lazy(500) as $j) {
                yield [$j->job_number, $j->school->school_code, $j->user?->name, $j->typeLabel(), $j->template_name, $j->total_items,
                    $j->completed_items, $j->failed_items, $j->status, $j->print_count, $j->ip_address, $j->created_at->format('Y-m-d H:i')];
            }
        })();

        return $exporter->download($format, 'print-history-'.now()->format('Ymd-His'), 'Print history', [
            'Job No.', 'School', 'User', 'Type', 'Template', 'Quantity', 'Completed', 'Failed', 'Status', 'Times printed', 'IP address', 'Date',
        ], $rows);
    }
}
