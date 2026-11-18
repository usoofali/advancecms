<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseRegistration;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class BatchCourseRegistrationService
{
    public function __construct(public GradingService $gradingService) {}

    /**
     * Register level and/or carryover courses for a chunk of students.
     *
     * @param  array<int, int|string>  $studentIds
     * @return array{students_processed: int, level_created: int, carryover_created: int, skipped: int}
     */
    public function registerChunk(
        array $studentIds,
        int $institutionId,
        int $sessionId,
        int $semesterId,
        bool $includeLevelCourses = true,
        bool $includeCarryovers = true
    ): array {
        $session = AcademicSession::findOrFail($sessionId);
        $semester = Semester::findOrFail($semesterId);
        $semesterNumber = strtolower(trim($semester->name)) === 'second' || str_contains(strtolower($semester->name), '2') ? 2 : 1;

        $students = Student::query()
            ->with(['program.department.gradingSystem', 'department.gradingSystem'])
            ->where('institution_id', $institutionId)
            ->whereIn('id', $studentIds)
            ->get();

        $studentsProcessed = 0;
        $levelCreated = 0;
        $carryoverCreated = 0;
        $skipped = 0;

        DB::transaction(function () use (
            $students,
            $institutionId,
            $session,
            $semester,
            $semesterNumber,
            $includeLevelCourses,
            $includeCarryovers,
            &$studentsProcessed,
            &$levelCreated,
            &$carryoverCreated,
            &$skipped
        ): void {
            foreach ($students as $student) {
                $studentsProcessed++;
                $currentLevel = $student->currentLevel($session);

                $existingCourseIds = CourseRegistration::query()
                    ->where('student_id', $student->id)
                    ->where('academic_session_id', $session->id)
                    ->where('semester_id', $semester->id)
                    ->pluck('course_id')
                    ->all();

                $carryoverCourses = $includeCarryovers
                    ? $this->gradingService->getCarryoverCourses($student, $institutionId, $session->id, $semester->id)
                    : collect();

                $carryoverIds = $carryoverCourses->pluck('id')->all();

                $levelCourses = collect();
                if ($includeLevelCourses) {
                    $levelCourses = Course::query()
                        ->where('institution_id', $institutionId)
                        ->where('status', 'active')
                        ->where('level', $currentLevel)
                        ->where('semester', $semesterNumber)
                        ->when($student->program_id, fn ($q) => $q->where('program_id', $student->program_id))
                        ->when(! $student->program_id && $student->department_id, fn ($q) => $q->where('department_id', $student->department_id))
                        ->get();
                }

                $allCourses = $levelCourses->merge($carryoverCourses)->unique('id');

                foreach ($allCourses as $course) {
                    if (in_array($course->id, $existingCourseIds, true)) {
                        $skipped++;

                        continue;
                    }

                    $isCarryover = in_array($course->id, $carryoverIds, true);

                    CourseRegistration::create([
                        'institution_id' => $institutionId,
                        'student_id' => $student->id,
                        'course_id' => $course->id,
                        'academic_session_id' => $session->id,
                        'semester_id' => $semester->id,
                        'status' => 'registered',
                        'is_carryover' => $isCarryover,
                    ]);

                    $existingCourseIds[] = $course->id;

                    if ($isCarryover) {
                        $carryoverCreated++;
                    } else {
                        $levelCreated++;
                    }
                }
            }
        });

        return [
            'students_processed' => $studentsProcessed,
            'level_created' => $levelCreated,
            'carryover_created' => $carryoverCreated,
            'skipped' => $skipped,
        ];
    }
}
