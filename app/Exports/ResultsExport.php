<?php

namespace App\Exports;

use App\Models\Result;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ResultsExport
{
    public int|string|null $institutionId;

    public int|string|null $sessionId;

    public int|string|null $semesterId;

    public function __construct(
        int|string|null $institutionId = null,
        int|string|null $sessionId = null,
        int|string|null $semesterId = null,
    ) {
        $this->institutionId = $institutionId;
        $this->sessionId = $sessionId;
        $this->semesterId = $semesterId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return Result::query()
            ->with(['student', 'course', 'academicSession', 'semester'])
            ->when($this->institutionId, fn ($q) => $q->where('institution_id', $this->institutionId))
            ->when($this->sessionId, fn ($q) => $q->where('academic_session_id', $this->sessionId))
            ->when($this->semesterId, fn ($q) => $q->where('semester_id', $this->semesterId))
            ->orderBy('id')
            ->get()
            ->map(fn (Result $r) => [
                'matric_number' => $r->student?->matric_number,
                'student_name' => $r->student?->full_name,
                'course_code' => $r->course?->course_code,
                'course_title' => $r->course?->title,
                'ca_score' => $r->ca_score,
                'exam_score' => $r->exam_score,
                'total_score' => $r->total_score,
                'grade' => $r->grade,
                'grade_point' => $r->grade_point,
                'remark' => $r->remark,
                'session' => $r->academicSession?->name,
                'semester' => $r->semester?->name,
            ])->all();
    }

    public function headings(): array
    {
        return [
            'matric_number', 'student_name', 'course_code', 'course_title',
            'ca_score', 'exam_score', 'total_score', 'grade', 'grade_point', 'remark',
            'session', 'semester',
        ];
    }

    public function download(): StreamedResponse
    {
        $rows = $this->rows();
        $headings = $this->headings();

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
            'Content-Disposition' => 'attachment; filename="results_export_'.date('Ymd_His').'.csv"',
        ]);
    }
}
