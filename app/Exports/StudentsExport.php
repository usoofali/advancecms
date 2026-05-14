<?php

namespace App\Exports;

use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentsExport
{
    public Builder $query;

    public function __construct(Builder $query)
    {
        $this->query = $query;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function rows(): array
    {
        return $this->query
            ->with('program.department')
            ->orderBy('matric_number')
            ->get()
            ->map(fn (Student $s) => [
                'matric_number' => $s->matric_number,
                'first_name' => $s->first_name,
                'last_name' => $s->last_name,
                'gender' => $s->gender,
                'date_of_birth' => $s->date_of_birth?->format('Y-m-d'),
                'email' => $s->email,
                'phone' => $s->phone,
                'program' => $s->program?->name,
                'program_acronym' => $s->program?->acronym,
                'admission_year' => $s->admission_year,
                'entry_level' => $s->entry_level,
                'status' => $s->status,
            ])->all();
    }

    public function headings(): array
    {
        return [
            'matric_number', 'first_name', 'last_name', 'gender', 'date_of_birth',
            'email', 'phone', 'program', 'program_acronym', 'admission_year', 'entry_level', 'status',
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
            'Content-Disposition' => 'attachment; filename="students_export_'.date('Ymd_His').'.csv"',
        ]);
    }
}
