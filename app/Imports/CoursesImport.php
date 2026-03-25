<?php

namespace App\Imports;

use App\Models\Course;
use App\Models\Program;
use Illuminate\Support\Str;

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
     * Import courses from an uploaded CSV file path.
     */
    public function import(string $filePath): void
    {
        $handle = fopen($filePath, 'r');

        if ($handle === false) {
            $this->failures[] = 'Could not open file.';

            return;
        }

        $headings = array_map('strtolower', array_map('trim', fgetcsv($handle)));
        $rowNumber = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($raw) !== count($headings)) {
                $this->failures[] = "Row {$rowNumber}: Column count mismatch.";

                continue;
            }

            $row = array_combine($headings, $raw);

            $missing = [];
            foreach (['course_code', 'title', 'credit_unit', 'level', 'semester', 'program_acronym'] as $field) {
                if (empty($row[$field])) {
                    $missing[] = $field;
                }
            }

            if (! empty($missing)) {
                $this->failures[] = "Row {$rowNumber}: Missing required fields: ".implode(', ', $missing);

                continue;
            }

            $program = Program::where('institution_id', $this->institutionId)
                ->where('acronym', trim($row['program_acronym']))
                ->first();

            if (! $program) {
                $this->failures[] = "Row {$rowNumber}: Program '{$row['program_acronym']}' not found.";

                continue;
            }

            try {
                Course::updateOrCreate(
                    [
                        'institution_id' => $this->institutionId,
                        'course_code' => strtoupper(str_replace(' ', '', $row['course_code'])),
                    ],
                    [
                        'program_id' => $program->id,
                        'department_id' => $program->department_id,
                        'title' => Str::title(trim($row['title'])),
                        'credit_unit' => (int) $row['credit_unit'],
                        'level' => (int) $row['level'],
                        'semester' => (int) $row['semester'],
                        'course_type' => ! empty($row['course_type']) ? trim($row['course_type']) : 'compulsory',
                        'status' => ! empty($row['status']) ? trim($row['status']) : 'active',
                    ]
                );

                $this->imported++;
            } catch (\Throwable $e) {
                $this->failures[] = "Row {$rowNumber}: {$e->getMessage()}";
            }
        }

        fclose($handle);
    }
}
