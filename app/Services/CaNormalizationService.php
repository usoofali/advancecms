<?php

namespace App\Services;

use App\Models\CaResult;
use App\Models\CaTest;

class CaNormalizationService
{
    /**
     * Maximum achievable normalized score for Continuous Assessment.
     */
    public const MAX_CA_SCORE = 30.00;

    /**
     * Normalize all graded CA tests for a specific student and course.
     */
    public function normalizeForCourse(int $courseId, int $studentId): void
    {
        // Get all graded tests for the course
        $gradedTests = CaTest::where('course_id', $courseId)
            ->where('test_type', 'graded')
            ->where('is_published', true)
            ->get();

        if ($gradedTests->isEmpty()) {
            return;
        }

        $totalObtainable = 0;
        $studentTotal = 0;

        foreach ($gradedTests as $test) {
            $maxTestMarks = $test->questions()->sum('marks');
            $totalObtainable += $maxTestMarks;

            $result = CaResult::where('ca_test_id', $test->id)
                ->where('student_id', $studentId)
                ->first();

            if ($result) {
                $studentTotal += $result->total_score;
            }
        }

        if ($totalObtainable > 0) {
            $normalizedScore = ($studentTotal / $totalObtainable) * self::MAX_CA_SCORE;
            $normalizedScore = min($normalizedScore, self::MAX_CA_SCORE); // Cap at 30 just in case

            // Update all results for this course with the new normalized score
            // This could also be stored in a separate `course_ca_totals` table in the future
            // For now, we store the combined normalized score on the individual result or calculate it on the fly.
            // Wait, the specification says: "Normalize all CA scores to a maximum of 30 marks regardless of number of tests."
            // We should store the `normalized_score` for the individual test result proportionally,
            // or store the cumulative normalized score somewhere.
            // Let's store the proportional normalized score on the CaResult.

            foreach ($gradedTests as $test) {
                $result = CaResult::where('ca_test_id', $test->id)
                    ->where('student_id', $studentId)
                    ->first();

                if ($result) {
                    // The student's normalized score for this specific test
                    // out of the total 30 marks available for the course
                    // Example: Test 1 is 10 marks, Test 2 is 20 marks. Total 30.
                    // If Test 1 max is 10, it's worth (10/30) * 30 = 10 normalized marks.
                    $maxTestMarks = $test->questions()->sum('marks');
                    $weight = $maxTestMarks / $totalObtainable;
                    $testNormalizedMax = $weight * self::MAX_CA_SCORE;

                    $resultNormalizedScore = ($result->total_score / $maxTestMarks) * $testNormalizedMax;

                    $result->update(['normalized_score' => $resultNormalizedScore]);
                }
            }
        }
    }
}
