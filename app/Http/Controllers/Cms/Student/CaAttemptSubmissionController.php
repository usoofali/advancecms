<?php

namespace App\Http\Controllers\Cms\Student;

use App\Http\Controllers\Controller;
use App\Models\CaAnswer;
use App\Models\CaAttempt;
use App\Services\CaGradingService;
use Illuminate\Http\Request;

class CaAttemptSubmissionController extends Controller
{
    public function __invoke(Request $request, CaAttempt $attempt, CaGradingService $gradingService)
    {
        // Ensure the student owns this attempt and it's in progress
        if ($attempt->student_id !== auth()->user()->student->id) {
            abort(403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json(['message' => 'Test already submitted'], 400);
        }

        $answers = $request->input('answers', []);

        foreach ($answers as $questionId => $optionId) {
            CaAnswer::create([
                'ca_attempt_id' => $attempt->id,
                'ca_question_id' => $questionId,
                'ca_question_option_id' => $optionId ?: null,
            ]);
        }

        $gradingService->gradeAttempt($attempt);

        return response()->json([
            'message' => 'Test submitted successfully',
            'score' => $attempt->answers()->sum('marks_earned'),
        ]);
    }
}
