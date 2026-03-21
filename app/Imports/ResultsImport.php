<?php

namespace App\Imports;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use App\Services\GradingService;

class ResultsImport
{
    /** @var array<int, string> */
    public array $failures = [];

    public int $imported = 0;

    public int|string $institutionId;

    public GradingService $gradingService;

    public function __construct(int|string $institutionId, GradingService $gradingService)
    {
        $this->institutionId = $institutionId;
        $this->gradingService = $gradingService;
    }

    /**
     * Import results from an uploaded CSV file path.
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
            foreach (['matric_number', 'course_code', 'ca_score', 'exam_score', 'session_name', 'semester_name'] as $field) {
                if (! isset($row[$field]) || $row[$field] === '') {
                    $missing[] = $field;
                }
            }

            if (! empty($missing)) {
                $this->failures[] = "Row {$rowNumber}: Missing required fields: ".implode(', ', $missing);

                continue;
            }

            $student = Student::where('institution_id', $this->institutionId)
                ->where('matric_number', trim($row['matric_number']))
                ->first();

            if (! $student) {
                $this->failures[] = "Row {$rowNumber}: Student '{$row['matric_number']}' not found.";

                continue;
            }

            $course = Course::where('institution_id', $this->institutionId)
                ->where('course_code', strtoupper(trim($row['course_code'])))
                ->first();

            if (! $course) {
                $this->failures[] = "Row {$rowNumber}: Course '{$row['course_code']}' not found.";

                continue;
            }

            $session = AcademicSession::where('name', trim($row['session_name']))->first();
            if (! $session) {
                $this->failures[] = "Row {$rowNumber}: Session '{$row['session_name']}' not found.";

                continue;
            }

            $semester = Semester::where('academic_session_id', $session->id)
                ->where('name', strtolower(trim($row['semester_name'])))
                ->first();

            if (! $semester) {
                $this->failures[] = "Row {$rowNumber}: Semester '{$row['semester_name']}' not found in session '{$row['session_name']}'.";

                continue;
            }

            try {
                $caScore = (float) $row['ca_score'];
                $examScore = (float) $row['exam_score'];
                $total = $caScore + $examScore;
                $graded = $this->gradingService->resolveGrade($total);

                Result::updateOrCreate(
                    [
                        'institution_id' => $this->institutionId,
                        'student_id' => $student->id,
                        'course_id' => $course->id,
                        'academic_session_id' => $session->id,
                        'semester_id' => $semester->id,
                    ],
                    [
                        'ca_score' => $caScore,
                        'exam_score' => $examScore,
                        'total_score' => $total,
                        'grade' => $graded['grade'],
                        'grade_point' => $graded['point'],
                        'remark' => $graded['remark'],
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
