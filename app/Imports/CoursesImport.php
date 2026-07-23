<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

class CoursesImport
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
     * Import courses from an uploaded file path (CSV or Excel).
     */
    public function import(string $filePath): void
    {
        @set_time_limit(300);

        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            Log::error("Course File Import Load Error: {$e->getMessage()}", ['exception' => $e]);
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

            $missing = [];
            foreach (['course_code', 'title', 'credit_unit', 'level', 'semester', 'program_acronym'] as $field) {
                if ($row[$field] === null || trim((string) $row[$field]) === '') {
                    $missing[] = $field;
                }
            }

            if (! empty($missing)) {
                $this->failures[] = "Row {$rowNumber}: Missing required fields: ".implode(', ', $missing);

                continue;
            }

            $program = Program::where('institution_id', $this->institutionId)
                ->where('acronym', strtoupper(trim($row['program_acronym'])))
                ->first();

            if (! $program) {
                $this->failures[] = "Row {$rowNumber}: Program '{$row['program_acronym']}' not found.";

                continue;
            }

            try {
                $rawCourseType = ! empty($row['course_type']) ? strtolower(trim($row['course_type'])) : 'core';
                $courseType = in_array($rawCourseType, ['core', 'elective']) ? $rawCourseType : 'core';

                Course::updateOrCreate(
                    [
                        'institution_id' => $this->institutionId,
                        'department_id' => $program->department_id,
                        'program_id' => $program->id,
                        'course_code' => strtoupper(str_replace(' ', '', $row['course_code'])),
                        'semester' => (int) $row['semester'],
                    ],
                    [
                        'title' => strtoupper(trim($row['title'])),
                        'credit_unit' => (int) $row['credit_unit'],
                        'level' => (int) $row['level'],
                        'course_type' => $courseType,
                        'status' => ! empty($row['status']) ? strtolower(trim($row['status'])) : 'active',
                    ]
                );

                $this->imported++;
            } catch (\Throwable $e) {
                $this->failures[] = "Row {$rowNumber}: {$e->getMessage()}";
            }
        }
    }
}
