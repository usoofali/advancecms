<?php

namespace App\Exports;

use App\Models\Student;
use App\Services\ResultsFilterService;
use App\Services\ResultsPresentationBuilder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FilteredResultsCsvExport
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected array $filters,
    ) {}

    public function download(): StreamedResponse
    {
        if (! ResultsFilterService::filterActive($this->filters['semester_id'] ?? null)) {
            abort(400, __('Select an academic semester before exporting.'));
        }

        $filename = 'results_export_'.date('Ymd_His').'.csv';

        if (ResultsFilterService::filterActive($this->filters['course_id'] ?? null)) {
            return $this->streamCourseCsv($filename);
        }

        return $this->streamMatrixCsv($filename);
    }

    protected function streamCourseCsv(string $filename): StreamedResponse
    {
        $headings = [
            'matric_number', 'student_name', 'course_code', 'course_title',
            'ca_score', 'exam_score', 'total_score', 'grade', 'remark',
            'session', 'semester',
        ];

        $query = ResultsFilterService::newFilteredQuery($this->filters)
            ->with(['student', 'course', 'academicSession', 'semester'])
            ->orderBy('id');

        $callback = function () use ($headings, $query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headings);
            $query->chunk(500, function ($chunk) use ($out) {
                foreach ($chunk as $r) {
                    fputcsv($out, [
                        $r->student?->matric_number,
                        $r->student?->full_name,
                        $r->course?->course_code,
                        $r->course?->title,
                        $r->ca_score,
                        $r->exam_score,
                        $r->total_score,
                        $r->grade,
                        $r->remark,
                        $r->academicSession?->name,
                        $r->semester?->name,
                    ]);
                }
            });
            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    protected function streamMatrixCsv(string $filename): StreamedResponse
    {
        $catalog = ResultsPresentationBuilder::catalogCourses($this->filters);
        $matrixFilters = ResultsPresentationBuilder::matrixFilters($this->filters);

        $callback = function () use ($catalog, $matrixFilters) {
            $out = fopen('php://output', 'w');
            $header = array_merge(
                ['matric_number', 'student_name'],
                $catalog->map(fn ($c) => $c->course_code)->all(),
                ['passes', 'fails'],
            );
            fputcsv($out, $header);

            $studentIds = ResultsPresentationBuilder::studentIdsInMatrixScope($matrixFilters);
            $courseIds = $catalog->pluck('id')->all();

            $studentIds->chunk(200)->each(function ($idChunk) use ($out, $catalog, $matrixFilters, $courseIds) {
                $ids = $idChunk->all();
                $students = Student::query()->whereIn('id', $ids)->orderBy('matric_number')->get()->keyBy('id');

                $results = ResultsFilterService::newFilteredQuery($matrixFilters)
                    ->whereIn('student_id', $ids)
                    ->whereIn('course_id', $courseIds)
                    ->get()
                    ->groupBy('student_id');

                foreach ($ids as $sid) {
                    $student = $students->get($sid);
                    if (! $student) {
                        continue;
                    }
                    $byCourse = $results->get($sid, collect())->keyBy('course_id');
                    $row = [$student->matric_number, $student->full_name];
                    $passes = 0;
                    $fails = 0;
                    foreach ($catalog as $course) {
                        $r = $byCourse->get($course->id);
                        if ($r) {
                            $row[] = (string) (float) $r->total_score.'/'.($r->grade ?? '—');
                            if ($r->remark === 'pass') {
                                $passes++;
                            } elseif ($r->remark === 'fail') {
                                $fails++;
                            }
                        } else {
                            $row[] = '';
                        }
                    }
                    $row[] = $passes;
                    $row[] = $fails;
                    fputcsv($out, $row);
                }
            });

            fclose($out);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
