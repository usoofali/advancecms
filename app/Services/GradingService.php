<?php

namespace App\Services;

use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Result;
use App\Models\Student;
use Illuminate\Support\Collection;

class GradingService
{
    /**
     * The grading scale: score ranges mapped to grade and grade point.
     *
     * @var array<int, array{min: int, grade: string, point: float}>
     */
    protected array $gradingScale = [
        ['min' => 70, 'grade' => 'A', 'point' => 5.0],
        ['min' => 60, 'grade' => 'B', 'point' => 4.0],
        ['min' => 50, 'grade' => 'C', 'point' => 3.0],
        ['min' => 45, 'grade' => 'D', 'point' => 2.0],
        ['min' => 40, 'grade' => 'E', 'point' => 1.0],
        ['min' => 0,  'grade' => 'F', 'point' => 0.0],
    ];

    /**
     * Resolve the grade and grade point from a total score.
     *
     * @return array{grade: string, point: float, remark: string}
     */
    public function resolveGrade(float $totalScore): array
    {
        foreach ($this->gradingScale as $scale) {
            if ($totalScore >= $scale['min']) {
                return [
                    'grade' => $scale['grade'],
                    'point' => $scale['point'],
                    'remark' => $scale['point'] > 0 ? 'pass' : 'fail',
                ];
            }
        }

        return ['grade' => 'F', 'point' => 0.0, 'remark' => 'fail'];
    }

    /**
     * Compute a student's GPA for a given set of results.
     * Applies best-grade-wins: for repeated courses, only the highest grade_point counts.
     *
     * @param  Collection<int, Result>  $results
     */
    public function computeGpa(Collection $results): float
    {
        // Deduplicate: keep only the best result per course
        $bestResults = $results
            ->groupBy('course_id')
            ->map(fn ($group) => $group->sortByDesc('grade_point')->first());

        $totalWeightedPoints = 0.0;
        $totalCreditUnits = 0;

        foreach ($bestResults as $result) {
            $creditUnit = $result->course->credit_unit;
            $totalWeightedPoints += $creditUnit * (float) $result->grade_point;
            $totalCreditUnits += $creditUnit;
        }

        if ($totalCreditUnits === 0) {
            return 0.0;
        }

        return round($totalWeightedPoints / $totalCreditUnits, 2);
    }

    /**
     * Compute a student's cumulative GPA (CGPA) across all sessions.
     * Uses best-grade-wins: only the highest attempt per course counts.
     */
    public function computeCgpa(Student $student): float
    {
        $results = $student->results()
            ->with('course')
            ->whereNotNull('grade_point')
            ->get();

        return $this->computeGpa($results);
    }

    /**
     * Get courses a student must register as carryover for the given session/semester.
     *
     * A carryover course is one where:
     *  - The student has at least one failed result (remark = 'fail')
     *  - The student has NOT yet passed the course (no result with remark = 'pass')
     *  - The course is NOT already registered for this session/semester
     *
     * @return Collection<int, Course>
     */
    public function getCarryoverCourses(
        Student $student,
        int $institutionId,
        int $sessionId,
        int $semesterId
    ): Collection {
        $allResults = $student->results()->with('course')->get();

        // Courses the student has failed at least once
        $failedCourseIds = $allResults
            ->where('remark', 'fail')
            ->pluck('course_id')
            ->unique();

        // Courses the student has already passed (remove from carryover list)
        $passedCourseIds = $allResults
            ->where('remark', 'pass')
            ->pluck('course_id')
            ->unique();

        // Actual outstanding carryover course IDs (failed but never passed)
        $carryoverIds = $failedCourseIds->diff($passedCourseIds);

        if ($carryoverIds->isEmpty()) {
            return collect();
        }

        // Remove courses already registered in the current session/semester
        $alreadyRegisteredIds = CourseRegistration::query()
            ->where('student_id', $student->id)
            ->where('academic_session_id', $sessionId)
            ->where('semester_id', $semesterId)
            ->pluck('course_id');

        $pendingCarryoverIds = $carryoverIds->diff($alreadyRegisteredIds);

        if ($pendingCarryoverIds->isEmpty()) {
            return collect();
        }

        return Course::whereIn('id', $pendingCarryoverIds)
            ->where('institution_id', $institutionId)
            ->get();
    }

    /**
     * Calculate and persist the total score, grade, grade point, and remark for a Result.
     */
    public function grade(Result $result): Result
    {
        $result->total_score = $result->ca_score + $result->exam_score;
        $graded = $this->resolveGrade($result->total_score);

        $result->grade = $graded['grade'];
        $result->grade_point = $graded['point'];
        $result->remark = $graded['remark'];
        $result->save();

        return $result;
    }

    /**
     * Helper to statically calculate grades without an existing Result model.
     * Useful for CSV imports where the result model is being built dynamically.
     *
     * @return array{total: float, grade: string, grade_point: float, remark: string}
     */
    public static function calculateGrades(float $caScore, float $examScore): array
    {
        $totalScore = $caScore + $examScore;

        $instance = new self;
        $graded = $instance->resolveGrade($totalScore);

        return [
            'total' => $totalScore,
            'grade' => $graded['grade'],
            'grade_point' => $graded['point'],
            'remark' => $graded['remark'],
        ];
    }
}
