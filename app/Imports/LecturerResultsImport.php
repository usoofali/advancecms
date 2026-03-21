<?php

namespace App\Imports;

use App\Models\CourseRegistration;
use App\Models\Result;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Support\Facades\Log;

class LecturerResultsImport
{
    /** @var array<int, string> */
    public array $failures = [];

    public int $imported = 0;

    public int|string $sessionId;

    public int|string $semesterId;

    public int|string $courseId;

    public int|string|null $institutionId;

    public function __construct(int|string|null $institutionId, int|string $sessionId, int|string $semesterId, int|string $courseId)
    {
        $this->institutionId = $institutionId;
        $this->sessionId = $sessionId;
        $this->semesterId = $semesterId;
        $this->courseId = $courseId;
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

        // Expects specifically matric_number, student_name, ca_score, exam_score in headers
        $expected = ['matric_number', 'student_name', 'ca_score', 'exam_score'];
        $missingHeadings = array_diff($expected, $headings);

        if (! empty($missingHeadings)) {
            $this->failures[] = 'Missing required column headers: '.implode(', ', $missingHeadings);
            fclose($handle);

            return;
        }

        $rowNumber = 1;

        while (($raw = fgetcsv($handle)) !== false) {
            $rowNumber++;

            if (count($raw) !== count($headings)) {
                $this->failures[] = "Row {$rowNumber}: Column count mismatch.";

                continue;
            }

            $row = array_combine($headings, $raw);

            // Require matric number
            if (empty($row['matric_number'])) {
                $this->failures[] = "Row {$rowNumber}: Missing matric_number.";

                continue;
            }

            $caScore = isset($row['ca_score']) && $row['ca_score'] !== '' ? (float) $row['ca_score'] : null;
            $examScore = isset($row['exam_score']) && $row['exam_score'] !== '' ? (float) $row['exam_score'] : null;

            // Optional: Skip row entirely if no scores provided
            if ($caScore === null && $examScore === null) {
                continue;
            }

            try {
                // Find Student (scoped to institution if available)
                $studentQuery = Student::where('matric_number', trim($row['matric_number']));
                if ($this->institutionId) {
                    $studentQuery->where('institution_id', $this->institutionId);
                }
                $student = $studentQuery->first();

                if (! $student) {
                    $this->failures[] = "Row {$rowNumber}: Student with matric number '{$row['matric_number']}' not found.";

                    continue;
                }

                // Verify Registration
                $registration = CourseRegistration::where('student_id', $student->id)
                    ->where('academic_session_id', $this->sessionId)
                    ->where('semester_id', $this->semesterId)
                    ->where('course_id', $this->courseId)
                    ->first();

                if (! $registration) {
                    $this->failures[] = "Row {$rowNumber}: {$student->full_name} ({$student->matric_number}) is not registered for this specific course session/semester.";

                    continue;
                }

                // Calculate grades
                $grading = GradingService::calculateGrades($caScore ?? 0, $examScore ?? 0);

                // Create or Update Result
                Result::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'course_id' => $this->courseId,
                        'academic_session_id' => $this->sessionId,
                        'semester_id' => $this->semesterId,
                    ],
                    [
                        'ca_score' => $caScore,
                        'exam_score' => $examScore,
                        'total_score' => $grading['total'],
                        'grade' => $grading['grade'],
                        'grade_point' => $grading['grade_point'],
                        'remark' => $grading['remark'],
                        'entered_by' => auth()->id(),
                    ]
                );

                $this->imported++;
            } catch (\Throwable $e) {
                Log::error("Result CSV Import Error Row {$rowNumber}: ".$e->getMessage(), ['exception' => $e]);
                $this->failures[] = "Row {$rowNumber}: An unexpected error occurred while processing '{$row['matric_number']}'.";
            }
        }

        fclose($handle);
    }
}
