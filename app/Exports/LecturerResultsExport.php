<?php

namespace App\Exports;

use App\Models\Course;
use App\Models\CourseRegistration;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LecturerResultsExport
{
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
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        $registrations = CourseRegistration::query()
            ->with(['student'])
            ->where('academic_session_id', $this->sessionId)
            ->where('semester_id', $this->semesterId)
            ->where('course_id', $this->courseId)
            ->whereHas('student', function ($query) {
                if ($this->institutionId) {
                    $query->where('institution_id', $this->institutionId);
                }
            })
            ->get();

        $results = \App\Models\Result::where('academic_session_id', $this->sessionId)
            ->where('semester_id', $this->semesterId)
            ->where('course_id', $this->courseId)
            ->get()
            ->keyBy('student_id');

        return $registrations->map(function ($registration) use ($results) {
            $result = $results->get($registration->student_id);
            return [
                'matric_number' => $registration->student->matric_number,
                'student_name' => $registration->student->full_name,
                'ca_score' => $result ? $result->ca_score : '',
                'exam_score' => $result ? $result->exam_score : '',
            ];
        })->all();
    }

    public function headings(): array
    {
        return [
            'matric_number',
            'student_name',
            'ca_score',
            'exam_score',
        ];
    }

    public function download(): StreamedResponse
    {
        $rows = $this->rows();
        $headings = $this->headings();

        $course = Course::findOrFail($this->courseId);
        $filename = "results_{$course->course_code}_{$this->sessionId}_{$this->semesterId}_".date('Ymd_His').'.csv';

        $callback = function () use ($rows, $headings) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headings);
            foreach ($rows as $row) {
                fputcsv($out, array_values($row));
            }
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
