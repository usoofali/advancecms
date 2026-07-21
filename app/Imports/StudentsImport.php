<?php

namespace App\Imports;

use App\Models\Program;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class StudentsImport
{
    /** @var array<int, string> */
    public array $failures = [];

    public int $imported = 0;

    public int|string $institutionId;

    public function __construct(int|string $institutionId)
    {
        $this->institutionId = $institutionId;
    }

    /**
     * Import students from an uploaded file path (CSV or Excel).
     */
    public function import(string $filePath): void
    {
        @set_time_limit(300);
        Student::$suppressEnrollmentNotification = true;

        try {
            try {
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();
                $rows = $sheet->toArray(null, true, true, false);
            } catch (\Throwable $e) {
                Log::error("Student File Import Load Error: {$e->getMessage()}", ['exception' => $e]);
                $this->failures[] = 'Could not open or parse file. Please upload a valid CSV or Excel file.';

                return;
            }

            if (empty($rows) || count($rows) < 1) {
                $this->failures[] = 'The uploaded file is empty.';

                return;
            }

            $headerRow = array_shift($rows);
            $headings = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);
            $rowNumber = 1;

            foreach ($rows as $rawValues) {
                $rowNumber++;

                // Skip empty rows
                if (empty(array_filter($rawValues, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                    continue;
                }

                if (count($rawValues) < count($headings)) {
                    $rawValues = array_pad($rawValues, count($headings), null);
                }

                $row = [];
                foreach ($headings as $index => $heading) {
                    $row[$heading] = $rawValues[$index] !== null ? trim((string) $rawValues[$index]) : '';
                }

                // Required fields
                $missing = [];
                foreach (['first_name', 'last_name', 'gender', 'program_acronym', 'admission_year', 'entry_level', 'email'] as $field) {
                    if (empty($row[$field])) {
                        $missing[] = $field;
                    }
                }

                if (! empty($missing)) {
                    $this->failures[] = "Row {$rowNumber}: Missing required fields: ".implode(', ', $missing);

                    continue;
                }

                // Lookup program
                $program = Program::where('institution_id', $this->institutionId)
                    ->where('acronym', strtoupper(trim($row['program_acronym'])))
                    ->first();

                if (! $program) {
                    $this->failures[] = "Row {$rowNumber}: Program with acronym '{$row['program_acronym']}' not found in this institution.";

                    continue;
                }

                // Check if email is already taken by a non-student user to prevent collisions
                $existingUser = User::where('email', trim($row['email']))->first();
                if ($existingUser && ! $existingUser->hasRole('Student')) {
                    $this->failures[] = "Row {$rowNumber}: Email '{$row['email']}' is already in use by a staff or admin account.";

                    continue;
                }

                try {
                    DB::transaction(function () use ($row, $program) {
                        Student::create([
                            'institution_id' => $this->institutionId,
                            'program_id' => $program->id,
                            'matric_number' => ! empty($row['matric_number']) ? trim($row['matric_number']) : null,
                            'first_name' => strtoupper(str_replace("'", '', trim($row['first_name']))),
                            'last_name' => strtoupper(str_replace("'", '', trim($row['last_name']))),
                            'gender' => strtolower(trim($row['gender'])),
                            'date_of_birth' => ! empty($row['date_of_birth']) ? trim($row['date_of_birth']) : null,
                            'email' => trim($row['email']),
                            'phone' => ! empty($row['phone']) ? trim($row['phone']) : null,
                            'admission_year' => (int) $row['admission_year'],
                            'entry_level' => (int) $row['entry_level'],
                            'status' => ! empty($row['status']) ? strtolower(trim($row['status'])) : 'active',
                        ]);
                    });

                    $this->imported++;
                } catch (\Throwable $e) {
                    $this->failures[] = "Row {$rowNumber}: {$e->getMessage()}";
                }
            }
        } finally {
            Student::$suppressEnrollmentNotification = false;
        }
    }
}
