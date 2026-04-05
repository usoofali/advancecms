<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Result;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ResultsPresentationBuilder
{
    /**
     * Curriculum courses for filters (same rules as results index course dropdown).
     *
     * @param  array<string, mixed>  $filters
     * @return EloquentCollection<int, Course>
     */
    public static function catalogCourses(array $filters): EloquentCollection
    {
        if (! ResultsFilterService::filterActive($filters['session_id'] ?? null)
            || ! ResultsFilterService::filterActive($filters['level'] ?? null)
            || ! ResultsFilterService::filterActive($filters['semester_id'] ?? null)) {
            return new EloquentCollection([]);
        }

        $query = Course::query()
            ->when(ResultsFilterService::filterActive($filters['institution_id'] ?? null), fn ($q) => $q->where('institution_id', $filters['institution_id']))
            ->when(ResultsFilterService::filterActive($filters['department_id'] ?? null), fn ($q) => $q->where('department_id', $filters['department_id']))
            ->when(ResultsFilterService::filterActive($filters['program_id'] ?? null), fn ($q) => $q->where('program_id', $filters['program_id']))
            ->where('level', (int) $filters['level']);

        $semester = Semester::query()->find($filters['semester_id']);
        if ($semester) {
            $query->where('semester', $semester->name === 'second' ? 2 : 1);
        }

        return $query->orderBy('course_code')->get();
    }

    /**
     * Filters for matrix / whole-semester views (no course_id).
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function matrixFilters(array $filters): array
    {
        $f = $filters;
        unset($f['course_id']);
        $f['course_id'] = '';

        return $f;
    }

    /**
     * Ordered student IDs that have at least one result in scope.
     *
     * @param  array<string, mixed>  $filters
     * @return Collection<int, int>
     */
    public static function studentIdsInMatrixScope(array $filters): Collection
    {
        $ids = ResultsFilterService::newFilteredQuery(self::matrixFilters($filters))
            ->distinct()
            ->pluck('student_id');

        return Student::query()
            ->whereIn('id', $ids)
            ->orderBy('matric_number')
            ->pluck('id')
            ->values();
    }

    /**
     * Matrix rows for an ordered list of student IDs (same shape as paginator items).
     *
     * @param  array<int, int>  $orderedStudentIds
     * @return Collection<int, array{student: Student, cells: array<int, string>, passes: int, fails: int}>
     */
    public static function matrixRowsForStudentIds(
        array $filters,
        EloquentCollection $catalogCourses,
        array $orderedStudentIds,
    ): Collection {
        if ($orderedStudentIds === []) {
            return collect();
        }

        $students = Student::query()
            ->whereIn('id', $orderedStudentIds)
            ->orderBy('matric_number')
            ->get()
            ->keyBy('id');

        $orderedStudents = collect($orderedStudentIds)->map(fn (int $id) => $students->get($id))->filter();

        $courseIds = $catalogCourses->pluck('id')->all();

        $results = ResultsFilterService::newFilteredQuery(self::matrixFilters($filters))
            ->whereIn('student_id', $orderedStudentIds)
            ->whereIn('course_id', $courseIds)
            ->get()
            ->groupBy('student_id');

        return $orderedStudents->map(function (Student $student) use ($catalogCourses, $results) {
            $byCourse = $results->get($student->id, collect())->keyBy('course_id');
            $cells = [];
            $passes = 0;
            $fails = 0;

            foreach ($catalogCourses as $course) {
                /** @var Result|null $r */
                $r = $byCourse->get($course->id);
                if ($r) {
                    $cells[$course->id] = (string) (float) $r->total_score.'/'.($r->grade ?? '—');
                    if ($r->remark === 'pass') {
                        $passes++;
                    } elseif ($r->remark === 'fail') {
                        $fails++;
                    }
                } else {
                    $cells[$course->id] = '';
                }
            }

            return [
                'student' => $student,
                'cells' => $cells,
                'passes' => $passes,
                'fails' => $fails,
            ];
        })->values();
    }

    /**
     * All matrix rows for print / export (no pagination).
     *
     * @return Collection<int, array{student: Student, cells: array<int, string>, passes: int, fails: int}>
     */
    public static function allMatrixRows(array $filters, EloquentCollection $catalogCourses): Collection
    {
        $ids = self::studentIdsInMatrixScope($filters)->all();

        return self::matrixRowsForStudentIds($filters, $catalogCourses, $ids);
    }

    /**
     * Aggregate pass/fail counts and percentages across matrix rows (per course outcome with a pass/fail remark).
     *
     * @param  Collection<int, array{student: Student, cells: array<int, string>, passes: int, fails: int}>  $matrixRows
     * @return array{total_passes: int, total_fails: int, pass_percentage: float, fail_percentage: float}
     */
    public static function matrixRowsPassFailSummary(Collection $matrixRows): array
    {
        $totalPasses = (int) $matrixRows->sum('passes');
        $totalFails = (int) $matrixRows->sum('fails');
        $denominator = $totalPasses + $totalFails;

        return [
            'total_passes' => $totalPasses,
            'total_fails' => $totalFails,
            'pass_percentage' => $denominator > 0 ? round(($totalPasses / $denominator) * 100, 1) : 0.0,
            'fail_percentage' => $denominator > 0 ? round(($totalFails / $denominator) * 100, 1) : 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, array{student: Student, cells: array<int, string>, passes: int, fails: int}>
     */
    public static function paginatedMatrixRows(
        array $filters,
        EloquentCollection $catalogCourses,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $studentIds = self::studentIdsInMatrixScope($filters);
        $total = $studentIds->count();
        $page = max(1, (int) LengthAwarePaginator::resolveCurrentPage());
        $slice = $studentIds->slice(($page - 1) * $perPage, $perPage)->values()->all();

        if ($slice === []) {
            return new LengthAwarePaginator([], $total, $perPage, $page, [
                'path' => LengthAwarePaginator::resolveCurrentPath(),
                'pageName' => 'page',
            ]);
        }

        $rows = self::matrixRowsForStudentIds($filters, $catalogCourses, $slice)->all();

        return new LengthAwarePaginator($rows, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]);
    }

    /**
     * Course-mode metrics from result rows (grade distribution, pass/fail counts).
     *
     * @param  Collection<int, Result>|array<Result>  $allResults
     * @return array<string, mixed>
     */
    public static function courseModeMetrics(Collection|array $allResults): array
    {
        $allResults = collect($allResults);
        $totalRows = $allResults->count();
        $totalPass = $allResults->where('remark', 'pass')->count();
        $totalFail = $allResults->where('remark', 'fail')->count();

        $grades = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'E' => 0, 'F' => 0];
        foreach ($allResults as $res) {
            if (isset($grades[$res->grade])) {
                $grades[$res->grade]++;
            }
        }

        $metrics = [
            'mode' => 'course',
            'total' => $totalRows,
            'pass' => $totalPass,
            'fail' => $totalFail,
            'pass_percentage' => $totalRows > 0 ? round(($totalPass / $totalRows) * 100, 1) : 0,
            'fail_percentage' => $totalRows > 0 ? round(($totalFail / $totalRows) * 100, 1) : 0,
            'grades' => [],
        ];

        foreach ($grades as $grade => $count) {
            $metrics['grades'][$grade] = [
                'count' => $count,
                'percentage' => $totalRows > 0 ? round(($count / $totalRows) * 100, 1) : 0,
            ];
        }

        return $metrics;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public static function matrixModeMetrics(array $filters, EloquentCollection $catalogCourses): array
    {
        $studentCount = self::studentIdsInMatrixScope($filters)->count();
        $courseCount = $catalogCourses->count();

        return [
            'mode' => 'matrix',
            'students' => $studentCount,
            'courses' => $courseCount,
        ];
    }
}
