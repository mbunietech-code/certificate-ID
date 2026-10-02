<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use App\Services\TableExporter;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(): View
    {
        $this->authorize('reports.view');

        return view('reports.index', ['reports' => ReportService::definitions()]);
    }

    public function show(Request $request, string $report, ReportService $reports, TableExporter $exporter): View|Response
    {
        $this->authorize('reports.view');

        $definition = ReportService::definitions()[$report] ?? abort(404);
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'format' => ['nullable', 'in:'.implode(',', TableExporter::FORMATS)],
        ]);
        $result = $reports->run($report, $filters);

        if ($format = $filters['format'] ?? null) {
            $this->audit('report.exported', null, "Exported report {$definition['title']} ({$format})", $filters);
            $school = $this->tenant()->school();

            return $exporter->download($format, $report.'-'.now()->format('Ymd'),
                $definition['title'].($school ? " – {$school->name}" : ''), $result['headers'], $result['rows']);
        }

        return view('reports.show', ['key' => $report, 'definition' => $definition, 'filters' => $filters] + $result);
    }
}
