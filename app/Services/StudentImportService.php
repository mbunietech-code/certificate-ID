<?php

namespace App\Services;

use App\Models\AcademicYear;
use App\Models\Student;
use App\Models\StudentImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\Csv;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Throwable;

/**
 * Two-step student import: parse() validates every row and stores a preview;
 * confirm() writes only the valid rows. Invalid rows are never imported and
 * are listed with their errors.
 */
class StudentImportService
{
    public const MAX_ROWS = 5000;

    /** Column aliases (normalised: lowercase alphanumerics only). */
    public const COLUMNS = [
        'admission_number' => ['admissionnumber', 'admissionno', 'admno', 'admission', 'regno', 'registrationnumber', 'indexnumber'],
        'first_name' => ['firstname', 'fname', 'givenname'],
        'middle_name' => ['middlename', 'mname', 'othername', 'othernames'],
        'last_name' => ['lastname', 'surname', 'lname', 'familyname'],
        'gender' => ['gender', 'sex'],
        'date_of_birth' => ['dateofbirth', 'dob', 'birthdate'],
        'level' => ['level', 'educationlevel'],
        'class_name' => ['class', 'form', 'classform', 'classname', 'grade'],
        'stream' => ['stream', 'section'],
        'combination' => ['combination', 'subjectcombination'],
        'entry_year' => ['entryyear', 'startingyear', 'startyear', 'admissionyear', 'yearofadmission'],
        'completion_year' => ['completionyear', 'graduationyear', 'finishingyear', 'yearofcompletion'],
        'academic_year' => ['academicyear', 'year', 'session'],
        'nationality' => ['nationality'],
        'parent_name' => ['parentname', 'guardianname', 'parent', 'guardian', 'parentguardian'],
        'parent_phone' => ['parentphone', 'guardianphone', 'parentcontact'],
        'student_phone' => ['studentphone', 'phone'],
        'address' => ['address', 'residence'],
    ];

    public const TEMPLATE_HEADERS = ['Admission Number', 'First Name', 'Middle Name', 'Last Name', 'Gender', 'Date of Birth',
        'Level', 'Class', 'Stream', 'Combination', 'Entry Year', 'Completion Year', 'Academic Year', 'Nationality', 'Parent Name', 'Parent Phone', 'Student Phone', 'Address'];

    public function parse(UploadedFile $file, int $schoolId, ?int $userId, string $duplicateMode): StudentImport
    {
        $raw = $this->readRows($file);
        if (count($raw) < 2) {
            throw ValidationException::withMessages(['file' => 'The file has no data rows. The first row must contain column headings.']);
        }

        $map = $this->mapHeaders(array_shift($raw));
        foreach (['admission_number', 'first_name', 'last_name', 'gender', 'class_name'] as $required) {
            if (! array_key_exists($required, $map)) {
                throw ValidationException::withMessages(['file' => 'Missing required column: '.str_replace('_', ' ', $required).'. Download the sample template for the expected headings.']);
            }
        }

        $raw = array_values(array_filter($raw, fn ($r) => count(array_filter($r, fn ($v) => trim((string) $v) !== '')) > 0));
        if (count($raw) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['file' => 'A single import is limited to '.self::MAX_ROWS.' rows. Split the file and import in parts.']);
        }

        $years = AcademicYear::forSchool($schoolId)->pluck('id', 'name')->mapWithKeys(fn ($id, $name) => [mb_strtolower($name) => $id]);
        $currentYear = AcademicYear::forSchool($schoolId)->where('is_current', true)->value('id');
        $existing = Student::withTrashed()->forSchool($schoolId)->pluck('id', 'admission_number')->mapWithKeys(fn ($id, $no) => [mb_strtolower($no) => $id]);

        $rows = [];
        $seen = [];
        foreach ($raw as $index => $cells) {
            $rowNumber = $index + 2;
            $data = [];
            foreach ($map as $field => $col) {
                $data[$field] = $this->clean($cells[$col] ?? null);
            }

            [$normalized, $errors] = $this->validateRow($data, $years->all(), $currentYear);
            $key = mb_strtolower((string) ($normalized['admission_number'] ?? ''));
            $status = $errors ? 'invalid' : 'valid';
            $existingId = null;

            if ($key !== '' && isset($seen[$key])) {
                $errors[] = "Duplicate admission number in file (also on row {$seen[$key]}).";
                $status = 'invalid';
            } elseif ($key !== '') {
                $seen[$key] = $rowNumber;
                if (! $errors && isset($existing[$key])) {
                    $status = 'duplicate';
                    $existingId = $existing[$key];
                }
            }

            $rows[] = ['row' => $rowNumber, 'data' => $normalized, 'errors' => $errors, 'status' => $status, 'existing_id' => $existingId];
        }

        $import = new StudentImport;
        $import->forceFill([
            'school_id' => $schoolId,
            'user_id' => $userId,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 250),
            'status' => 'preview',
            'duplicate_mode' => $duplicateMode,
            'rows' => $rows,
            'summary' => $this->summarize($rows),
        ])->save();

        return $import;
    }

    /** @return array{created: int, updated: int, skipped: int, invalid: int} */
    public function confirm(StudentImport $import): array
    {
        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'invalid' => 0];

        DB::transaction(function () use ($import, &$result) {
            foreach (array_chunk($import->rows, 200) as $chunk) {
                foreach ($chunk as $row) {
                    if ($row['status'] === 'invalid') {
                        $result['invalid']++;

                        continue;
                    }

                    $attributes = collect($row['data'])->except('academic_year')->all();
                    // Re-check: a student may have been added since the preview.
                    $student = Student::withTrashed()->forSchool($import->school_id)->where('admission_number', $attributes['admission_number'])->first();

                    if ($student) {
                        if ($import->duplicate_mode !== 'update') {
                            $result['skipped']++;

                            continue;
                        }
                        $student->fill(array_filter($attributes, fn ($v) => $v !== null && $v !== ''));
                        if ($student->trashed()) {
                            $student->restore();
                        }
                        $student->save();
                        $result['updated']++;
                    } else {
                        $student = new Student($attributes + ['status' => 'active']);
                        $student->school_id = $import->school_id;
                        $student->save();
                        $result['created']++;
                    }
                }
            }

            $import->forceFill(['status' => 'completed', 'summary' => array_merge($import->summary ?? [], ['result' => $result])])->save();
        });

        return $result;
    }

    /**
     * @param  array<string, string|null>  $data
     * @param  array<string, int>  $years  lowercase name => id
     * @return array{0: array<string, mixed>, 1: array<int, string>}
     */
    private function validateRow(array $data, array $years, ?int $currentYear): array
    {
        $errors = [];

        $gender = mb_strtolower((string) ($data['gender'] ?? ''));
        $data['gender'] = match (true) {
            in_array($gender, ['m', 'male', 'boy', 'me', 'mvulana'], true) => 'male',
            in_array($gender, ['f', 'female', 'girl', 'ke', 'msichana'], true) => 'female',
            default => $gender,
        };

        $level = preg_replace('/[^a-z]/', '', mb_strtolower((string) ($data['level'] ?? '')));
        $data['level'] = match ($level) {
            'olevel', 'o', 'ordinary', 'ordinarylevel' => 'O-Level',
            'alevel', 'a', 'advanced', 'advancedlevel' => 'A-Level',
            default => $data['level'] ?? null,
        };

        if (! empty($data['date_of_birth'])) {
            $date = $this->parseDate($data['date_of_birth']);
            if (! $date) {
                $errors[] = "Date of birth \"{$data['date_of_birth']}\" is not a valid date (use YYYY-MM-DD or DD/MM/YYYY).";
            }
            $data['date_of_birth'] = $date;
        }

        $data['academic_year_id'] = $currentYear;
        if (! empty($data['academic_year'])) {
            $id = $years[mb_strtolower($data['academic_year'])] ?? null;
            if (! $id) {
                $errors[] = "Academic year \"{$data['academic_year']}\" does not exist for this school.";
            }
            $data['academic_year_id'] = $id;
        }

        $validator = Validator::make($data, [
            'admission_number' => ['required', 'max:40'],
            'first_name' => ['required', 'max:60'],
            'middle_name' => ['nullable', 'max:60'],
            'last_name' => ['required', 'max:60'],
            'gender' => ['required', Rule::in(Student::GENDERS)],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'class_name' => ['required', 'max:40'],
            'stream' => ['nullable', 'max:30'],
            'combination' => ['nullable', 'max:40'],
            'level' => ['nullable', 'max:20'],
            'entry_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'completion_year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'nationality' => ['nullable', 'max:60'],
            'parent_name' => ['nullable', 'max:120'],
            'parent_phone' => ['nullable', 'max:40'],
            'student_phone' => ['nullable', 'max:40'],
            'address' => ['nullable', 'max:255'],
        ], [], [
            'admission_number' => 'admission number', 'first_name' => 'first name', 'last_name' => 'last name', 'class_name' => 'class',
        ]);

        $errors = array_merge($errors, $validator->errors()->all());
        $known = array_keys(self::COLUMNS);
        $normalized = array_intersect_key($data, array_flip(array_merge($known, ['academic_year_id'])));
        unset($normalized['academic_year']);
        $normalized['academic_year'] = $data['academic_year'] ?? null;

        return [$normalized, array_values(array_unique($errors))];
    }

    /** @return array<int, array<int, mixed>> */
    private function readRows(UploadedFile $file): array
    {
        try {
            $ext = strtolower($file->getClientOriginalExtension());
            if (in_array($ext, ['csv', 'txt'], true)) {
                $reader = new Csv;
                $reader->setInputEncoding(Csv::GUESS_ENCODING);
                $spreadsheet = $reader->load($file->getRealPath());
            } else {
                $reader = IOFactory::createReaderForFile($file->getRealPath());
                $reader->setReadDataOnly(true);
                $spreadsheet = $reader->load($file->getRealPath());
            }

            return $spreadsheet->getActiveSheet()->toArray(null, true, false, false);
        } catch (Throwable $e) {
            throw ValidationException::withMessages(['file' => 'The file could not be read. Upload a valid .xlsx, .xls or .csv file.']);
        }
    }

    /** @param  array<int, mixed>  $headers @return array<string, int> field => column index */
    private function mapHeaders(array $headers): array
    {
        $map = [];
        foreach ($headers as $index => $heading) {
            $normalized = preg_replace('/[^a-z0-9]/', '', mb_strtolower((string) $heading));
            foreach (self::COLUMNS as $field => $aliases) {
                if (! isset($map[$field]) && ($normalized === str_replace('_', '', $field) || in_array($normalized, $aliases, true))) {
                    $map[$field] = $index;
                    break;
                }
            }
        }

        return $map;
    }

    private function clean(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_float($value) && floor($value) === $value) {
            $value = (int) $value;
        }
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    private function parseDate(string $value): ?string
    {
        if (is_numeric($value) && (float) $value > 1000 && (float) $value < 80000) {
            return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value))->toDateString();
        }

        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'm/d/Y'] as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);
            if ($date && $date->format($format) === $value) {
                return $date->format('Y-m-d');
            }
        }

        return null;
    }

    /** @param  array<int, array<string, mixed>>  $rows */
    private function summarize(array $rows): array
    {
        $counts = array_count_values(array_column($rows, 'status'));

        return [
            'total' => count($rows),
            'valid' => $counts['valid'] ?? 0,
            'duplicate' => $counts['duplicate'] ?? 0,
            'invalid' => $counts['invalid'] ?? 0,
        ];
    }
}
