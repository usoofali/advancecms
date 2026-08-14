<?php

namespace App\Actions\Placements;

use App\Models\AcademicSession;
use App\Models\PlacementEvaluation;
use App\Models\StudentPlacement;
use Illuminate\Support\Carbon;

class SubmitPlacementEvaluationAction
{
    public function execute(
        StudentPlacement $placement,
        int $supervisorId,
        int $punctuality,
        int $attendance,
        int $conduct,
        int $technical,
        int $logbook,
        ?string $remarks = null
    ): PlacementEvaluation {
        $sumRatings = $punctuality + $attendance + $conduct + $technical + $logbook;
        // 5 metrics * 5 points max = 25 max points. Total score out of 100 = (sum / 25) * 100
        $totalScore = round(($sumRatings / 25) * 100, 2);

        $grade = match (true) {
            $totalScore >= 70 => 'A',
            $totalScore >= 60 => 'B',
            $totalScore >= 50 => 'C',
            $totalScore >= 45 => 'D',
            default => 'F',
        };

        $sessionId = null;
        if (is_numeric($placement->academic_session)) {
            $sessionId = (int) $placement->academic_session;
        } elseif ($placement->academic_session) {
            $sessionId = AcademicSession::where('name', $placement->academic_session)->value('id');
        }

        if (! $sessionId) {
            $sessionId = AcademicSession::where('status', 'active')->value('id');
        }

        return PlacementEvaluation::updateOrCreate(
            ['placement_id' => $placement->id],
            [
                'student_id' => $placement->student_id,
                'supervisor_id' => $supervisorId,
                'academic_session_id' => $sessionId,
                'punctuality_rating' => $punctuality,
                'attendance_rating' => $attendance,
                'conduct_discipline_rating' => $conduct,
                'technical_skills_rating' => $technical,
                'logbook_maintenance_rating' => $logbook,
                'total_score' => $totalScore,
                'performance_grade' => $grade,
                'supervisor_remarks' => $remarks,
                'evaluated_at' => Carbon::now(),
            ]
        );
    }
}
