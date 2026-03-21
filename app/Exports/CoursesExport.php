<?php

namespace App\Exports;

use App\Models\Course;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CoursesExport
{
    public int|string|null $institutionId;

    public function __construct(int|string|null $institutionId = null)
    {
        $this->institutionId = $institutionId;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return Course::query()
            ->with(['program', 'department'])
            ->when($this->institutionId, fn ($q) => $q->where('institution_id', $this->institutionId))
            ->orderBy('course_code')
            ->get()
            ->map(fn (Course $c) => [
                'course_code' => $c->course_code,
                'title' => $c->title,
                'credit_unit' => $c->credit_unit,
                'level' => $c->level,
                'semester' => $c->semester,
                'course_type' => $c->course_type,
                'status' => $c->status,
                'program_acronym' => $c->program?->acronym,
                'department' => $c->department?->name,
            ])->all();
    }

    public function headings(): array
    {
        return [
            'course_code', 'title', 'credit_unit', 'level', 'semester',
            'course_type', 'status', 'program_acronym', 'department',
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
            'Content-Disposition' => 'attachment; filename="courses_export_'.date('Ymd_His').'.csv"',
        ]);
    }
}
