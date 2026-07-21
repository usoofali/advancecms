<?php

namespace App\Imports;

use App\Models\CourseRegistration;
use App\Models\Result;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
     * Import results from an uploaded file path (CSV or Excel).
     */
    public function import(string $filePath): void
    {
        @set_time_limit(300);
        try {
            $spreadsheet = IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            Log::error("Result File Import Load Error: {$e->getMessage()}", ['exception' => $e]);
            $this->failures[] = 'Could not open or parse file. Please upload a valid CSV or Excel file.';

            return;
        }

        if (empty($rows) || count($rows) < 1) {
            $this->failures[] = 'The uploaded file is empty.';

            return;
        }

        $headerRow = array_shift($rows);
        $headings = array_map(fn ($h) => strtolower(trim((string) $h)), $headerRow);

        // Expects specifically matric_number, student_name, ca_score, exam_score in headers
        $expected = ['matric_number', 'student_name', 'ca_score', 'exam_score'];
        $missingHeadings = array_diff($expected, $headings);

        if (! empty($missingHeadings)) {
            $this->failures[] = 'Missing required column headers: '.implode(', ', $missingHeadings);

            return;
        }

        $rowNumber = 1;

        foreach ($rows as $rawValues) {
            $rowNumber++;

            // Ignore empty rows
            if (empty(array_filter($rawValues, fn ($v) => $v !== null && trim((string) $v) !== ''))) {
                continue;
            }

            if (count($rawValues) < count($headings)) {
                $rawValues = array_pad($rawValues, count($headings), null);
            }

            $row = [];
            foreach ($headings as $index => $heading) {
                $row[$heading] = $rawValues[$index] ?? null;
            }

            // Require matric number
            $matricNumber = trim((string) ($row['matric_number'] ?? ''));
            if ($matricNumber === '') {
                $this->failures[] = "Row {$rowNumber}: Missing matric_number.";

                continue;
            }

            $caVal = $row['ca_score'] ?? null;
            $examVal = $row['exam_score'] ?? null;

            $caScore = ($caVal !== null && trim((string) $caVal) !== '') ? (float) $caVal : null;
            $examScore = ($examVal !== null && trim((string) $examVal) !== '') ? (float) $examVal : null;

            // Optional: Skip row entirely if no scores provided
            if ($caScore === null && $examScore === null) {
                continue;
            }

            try {
                // Find Student (scoped to institution if available)
                $studentQuery = Student::where('matric_number', $matricNumber);
                if ($this->institutionId) {
                    $studentQuery->where('institution_id', $this->institutionId);
                }
                $student = $studentQuery->first();

                if (! $student) {
                    $this->failures[] = "Row {$rowNumber}: Student with matric number '{$matricNumber}' not found.";

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

                $existingResult = Result::where('student_id', $student->id)
                    ->where('course_id', $this->courseId)
                    ->where('semester_id', $this->semesterId)
                    ->first();

                $finalCa = $caScore !== null ? $caScore : ($existingResult ? $existingResult->ca_score : 0);
                $finalExam = $examScore !== null ? $examScore : ($existingResult ? $existingResult->exam_score : 0);

                // Calculate grades
                $grading = GradingService::calculateGrades($finalCa, $finalExam, $student->program?->department);

                // Create or Update Result
                Result::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'course_id' => $this->courseId,
                        'semester_id' => $this->semesterId,
                    ],
                    [
                        'academic_session_id' => $this->sessionId,
                        'ca_score' => $finalCa,
                        'exam_score' => $finalExam,
                        'total_score' => $grading['total'],
                        'grade' => $grading['grade'],
                        'grade_point' => $grading['grade_point'],
                        'remark' => $grading['remark'],
                        'entered_by' => auth()->id(),
                    ]
                );

                $this->imported++;
            } catch (\Throwable $e) {
                Log::error("Result File Import Error Row {$rowNumber}: {$e->getMessage()}", ['exception' => $e]);
                $this->failures[] = "Row {$rowNumber}: An unexpected error occurred while processing '{$matricNumber}'.";
            }
        }
    }
}
