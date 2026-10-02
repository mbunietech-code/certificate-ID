<?php

namespace App\Http\Controllers;

use App\Models\StudentImport;
use App\Services\StudentImportService;
use App\Services\TableExporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class StudentImportController extends Controller
{
    public function create(): View
    {
        $this->authorize('students.import');

        $recent = StudentImport::with('user:id,name')->latest('id')->limit(10)->get(['id', 'school_id', 'user_id', 'original_name', 'status', 'summary', 'created_at']);

        return view('students.import.create', compact('recent'));
    }

    public function store(Request $request, StudentImportService $service): RedirectResponse
    {
        $this->authorize('students.import');

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:xlsx,xls,csv,txt'],
            'duplicate_mode' => ['required', Rule::in(['skip', 'update'])],
        ]);

        $import = $service->parse($request->file('file'), $this->tenant()->schoolIdForWrite(), $request->user()->id, $request->input('duplicate_mode'));

        return redirect()->route('students.import.show', $import);
    }

    public function show(Request $request, StudentImport $import): View
    {
        $this->authorize('students.import');

        $filter = $request->query('show', 'all');
        $rows = collect($import->rows)->when($filter !== 'all', fn ($c) => $c->where('status', $filter))->values();

        return view('students.import.show', [
            'import' => $import,
            'rows' => $rows->take(1000),
            'filter' => $filter,
            'truncated' => $rows->count() > 1000,
        ]);
    }

    public function confirm(StudentImport $import, StudentImportService $service): RedirectResponse
    {
        $this->authorize('students.import');
        abort_unless($import->status === 'preview', 409, 'This import was already processed.');

        $result = $service->confirm($import);
        $this->audit('student.imported', $import, "Imported students from {$import->original_name}", $result);

        return redirect()->route('students.index')->with('success',
            "Import complete: {$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped (already exist), {$result['invalid']} invalid row(s) not imported.");
    }

    public function destroy(StudentImport $import): RedirectResponse
    {
        $this->authorize('students.import');

        if ($import->status === 'preview') {
            $import->forceFill(['status' => 'cancelled', 'rows' => []])->save();
        }

        return redirect()->route('students.import')->with('success', 'Import cancelled. Nothing was imported.');
    }

    public function template(TableExporter $exporter): Response
    {
        $this->authorize('students.import');

        $year = (string) now()->year;

        return $exporter->download('xlsx', 'student-import-template', 'Students', StudentImportService::TEMPLATE_HEADERS, [
            ['ADM/0001', 'Amina', 'Juma', 'Hassan', 'Female', '2010-03-14', 'O-Level', 'Form IV', 'A', '', (int) $year - 3, $year, $year, 'Tanzanian', 'Juma Hassan', '+255711111111', '', 'Dar es Salaam'],
            ['ADM/0002', 'Baraka', '', 'Mwita', 'Male', '14/07/2007', 'A-Level', 'Form VI', 'B', 'PCB', (int) $year - 1, $year, $year, 'Tanzanian', 'Rose Mwita', '+255722222222', '', 'Mwanza'],
        ]);
    }
}
