<?php

namespace App\Services\Cbt;

use App\Models\AcademicSession;
use App\Models\Semester;
use App\Models\StudentCbtProfile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PinGeneratorService
{
    /**
     * Generate or rotate CBT PINs for a collection of students for the current active session/semester.
     */
    public function generateForStudents(Collection $students, ?AcademicSession $session = null, ?Semester $semester = null): array
    {
        $session = $session ?? AcademicSession::active()->first();
        $semester = $semester ?? Semester::where('academic_session_id', $session?->id)->first(); // Default to first semester of active session

        if (! $session || ! $semester) {
            return [
                'success' => false,
                'message' => 'No active academic session or semester found.',
            ];
        }

        $count = 0;

        DB::transaction(function () use ($students, $session, $semester, &$count) {
            foreach ($students as $student) {
                StudentCbtProfile::firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_session_id' => $session->id,
                        'semester_id' => $semester->id,
                    ],
                    [
                        'cbt_pin' => $this->generateUniquePin($semester->id),
                        'last_generated_at' => now(),
                    ]
                );
                $count++;
            }
        });

        return [
            'success' => true,
            'count' => $count,
            'session' => $session->name,
            'semester' => $semester->name,
        ];
    }

    /**
     * Generate a random 6-digit numeric PIN.
     */
    private function generateUniquePin(int $semesterId): string
    {
        // Simple numeric PIN generation.
        // Collision risk for 6 digits is low enough for a single institution/semester.
        return (string) random_int(111111, 999999);
    }
}
