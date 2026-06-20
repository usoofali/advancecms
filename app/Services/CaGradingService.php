<?php

namespace App\Services;

use App\Models\CaAttempt;
use App\Models\CaResult;
use App\Models\StudentCoin;

class CaGradingService
{
    public function gradeAttempt(CaAttempt $attempt): void
    {
        $totalScore = 0;
        $totalCoins = 0;

        foreach ($attempt->answers as $answer) {
            $question = $answer->caQuestion;
            $selectedOption = $answer->caQuestionOption;

            if ($selectedOption && $selectedOption->is_correct) {
                $answer->update([
                    'is_correct' => true,
                    'marks_earned' => $question->marks,
                    'coins_earned' => $question->coin_reward,
                ]);

                $totalScore += $question->marks;
                $totalCoins += $question->coin_reward;
            } else {
                $answer->update([
                    'is_correct' => false,
                    'marks_earned' => 0,
                    'coins_earned' => 0,
                ]);
            }
        }

        $attempt->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);

        if ($attempt->caTest->test_type === 'graded') {
            $this->saveResult($attempt, $totalScore);
        }

        if ($attempt->caTest->coin_reward_enabled && $totalCoins > 0) {
            $this->awardCoins($attempt->student_id, $totalCoins);
        }
    }

    protected function saveResult(CaAttempt $attempt, float $totalScore): void
    {
        $result = CaResult::firstOrNew([
            'ca_test_id' => $attempt->ca_test_id,
            'student_id' => $attempt->student_id,
        ]);

        // Default multiple attempts logic: Highest score
        if ($result->exists) {
            $result->attempt_count += 1;
            if ($totalScore > $result->total_score) {
                $result->total_score = $totalScore;
            }
            $result->save();
        } else {
            $result->total_score = $totalScore;
            $result->attempt_count = 1;
            $result->save();
        }

        // Trigger normalization
        app(CaNormalizationService::class)->normalizeForCourse($attempt->caTest->course_id, $attempt->student_id);
    }

    protected function awardCoins(int $studentId, int $coins): void
    {
        $studentCoin = StudentCoin::firstOrCreate(
            ['student_id' => $studentId],
            ['total_coins' => 0]
        );

        $studentCoin->increment('total_coins', $coins);
    }
}
