<?php

namespace App\Services\SmartSchool;

use App\Models\School;
use App\Models\StudentImport;
use App\Services\StudentImportService;
use Illuminate\Validation\ValidationException;

class SmartSchoolStudentImportService
{
    public function __construct(
        private readonly SmartSchoolPeopleSource $source,
        private readonly StudentImportService $imports,
    ) {}

    public function preview(School $school, ?int $userId, string $duplicateMode): StudentImport
    {
        $people = $this->source->fetch($school);

        if (count($people['students']) === 0) {
            throw ValidationException::withMessages([
                'smart_school' => 'Smart School returned no student records.',
            ]);
        }

        $import = $this->imports->previewMappedRows(
            $people['students'],
            'Smart School - '.$people['source'],
            $school->id,
            $userId,
            $duplicateMode,
        );

        $import->forceFill([
            'summary' => array_merge($import->summary ?? [], [
                'source' => $people['source'],
                'staff_available' => count($people['staff']),
            ]),
        ])->save();

        return $import;
    }
}
