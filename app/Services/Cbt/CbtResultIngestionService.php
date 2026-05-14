<?php

namespace App\Services\Cbt;

use App\Models\CbtExam;
use App\Models\CbtResultStaging;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class CbtResultIngestionService
{
    /**
     * Ingest results from the CBT Lab Server.
     */
    public function ingest(array $data): array
    {
        $examUuid = $data['exam_uuid'] ?? null;
        $submissionToken = $data['submission_token'] ?? null;
        $results = $data['results'] ?? [];

        if (! $examUuid || ! $submissionToken) {
            return ['success' => false, 'message' => 'Missing exam UUID or submission token.'];
        }

        $exam = CbtExam::where('uuid', $examUuid)->first();
        if (! $exam) {
            return ['success' => false, 'message' => 'Exam not found.'];
        }

        $count = 0;
        $skipped = 0;

        DB::transaction(function () use ($exam, $submissionToken, $results, &$count, &$skipped) {
            foreach ($results as $result) {
                $matricNo = $result['matric_no'] ?? null;
                $student = Student::where('matric_number', $matricNo)->first();

                if (! $student) {
                    $skipped++;

                    continue;
                }

                // Unique identifier for this specific student's attempt in this submission
                $uniqueToken = $submissionToken.'_'.$student->id;

                $attemptNum = $result['attempt'] ?? 1;

                $existing = CbtResultStaging::where('cbt_exam_id', $exam->id)
                    ->where('student_id', $student->id)
                    ->where('attempt_number', $attemptNum)
                    ->first();

                if ($existing) {
                    // If already processed, we shouldn't overwrite it
                    if ($existing->status === 'processed') {
                        $skipped++;

                        continue;
                    }

                    // Update existing pending record with fresh pull data
                    $existing->update([
                        'score_raw' => $result['score_raw'] ?? 0,
                        'score_percent' => $result['score_percent'] ?? 0,
                        'responses' => $result['responses'] ?? [],
                        'submission_token' => $uniqueToken,
                        'synced_at' => now(),
                    ]);
                } else {
                    CbtResultStaging::create([
                        'cbt_exam_id' => $exam->id,
                        'student_id' => $student->id,
                        'attempt_number' => $attemptNum,
                        'attempt_type' => $result['attempt_type'] ?? ($attemptNum > 1 ? 'resit' : 'normal'),
                        'score_raw' => $result['score_raw'] ?? 0,
                        'score_percent' => $result['score_percent'] ?? 0,
                        'responses' => $result['responses'] ?? [],
                        'submission_token' => $uniqueToken,
                        'status' => 'pending',
                        'synced_at' => now(),
                    ]);
                }

                $count++;
            }
        });

        return [
            'success' => true,
            'ingested' => $count,
            'skipped' => $skipped,
        ];
    }
}
