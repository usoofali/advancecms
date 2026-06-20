<?php

namespace App\Http\Controllers\Cms\Student;

use App\Http\Controllers\Controller;
use App\Models\CaAttempt;
use Illuminate\Http\Request;

class CaAttemptTimeExtensionController extends Controller
{
    public function __invoke(Request $request, CaAttempt $attempt)
    {
        // Ensure the student owns this attempt and it's in progress
        if ($attempt->student_id !== auth()->user()->student->id) {
            abort(403);
        }

        if ($attempt->status !== 'in_progress') {
            return response()->json(['message' => 'Test already submitted or expired'], 400);
        }

        $offlineDuration = (int) $request->input('offline_seconds', 0);

        // Sanity check: prevent absurdly large extensions (e.g. more than the test duration)
        // This is a basic safety mechanism so students can't game the system indefinitely.
        $maxExtension = ($attempt->caTest->duration_minutes * 60) ?: 3600;
        if ($offlineDuration > $maxExtension) {
            $offlineDuration = $maxExtension;
        }

        if ($offlineDuration > 0 && $attempt->deadline_at) {
            $attempt->deadline_at = $attempt->deadline_at->addSeconds($offlineDuration);
            $attempt->save();
        }

        // Return the new remaining seconds
        $remainingSeconds = max(0, now()->diffInSeconds($attempt->deadline_at, false));

        return response()->json([
            'message' => 'Time extended successfully',
            'remainingSeconds' => $remainingSeconds < 0 ? 0 : $remainingSeconds
        ]);
    }
}
