<?php

namespace App\Services\Cbt;

use App\Models\CbtExam;
use App\Models\CbtOption;
use App\Models\CbtQuestion;
use Illuminate\Support\Facades\DB;

class QuestionImporterService
{
    /**
     * Import questions from a CSV file for a specific exam.
     * Expected columns: question_text, option1, option2, option3, option4, correct_indices (e.g. "1" or "1,3"), marks, media_path
     */
    public function importFromCsv(CbtExam $exam, string $filePath): array
    {
        if (! file_exists($filePath) || ! is_readable($filePath)) {
            return ['success' => false, 'message' => 'File not found or not readable.'];
        }

        $handle = fopen($filePath, 'r');
        $header = fgetcsv($handle);

        // Basic validation of headers
        if (! $header || count($header) < 5) {
            fclose($handle);

            return ['success' => false, 'message' => 'Invalid CSV format. Missing required columns.'];
        }

        $count = 0;
        $errors = [];
        $rowNumber = 1;

        DB::transaction(function () use ($handle, $exam, &$count, &$errors, &$rowNumber) {
            while (($data = fgetcsv($handle)) !== false) {
                $rowNumber++;

                // Map data to named keys (assuming order: question, o1, o2, o3, o4, correct, marks, media)
                $row = [
                    'question' => $data[0] ?? null,
                    'o1' => $data[1] ?? null,
                    'o2' => $data[2] ?? null,
                    'o3' => $data[3] ?? null,
                    'o4' => $data[4] ?? null,
                    'correct' => $data[5] ?? '1',
                    'marks' => $data[6] ?? 1,
                    'media' => $data[7] ?? null,
                ];

                if (empty($row['question'])) {
                    $errors[] = "Row {$rowNumber}: Question text is missing.";

                    continue;
                }

                $correctIndices = explode(',', $row['correct']);
                $isMultiple = count($correctIndices) > 1;

                $question = CbtQuestion::create([
                    'cbt_exam_id' => $exam->id,
                    'question_text' => $row['question'],
                    'media_path' => $row['media'],
                    'type' => $isMultiple ? 'multiple' : 'single',
                    'marks' => (float) $row['marks'],
                ]);

                // Options
                for ($i = 1; $i <= 4; $i++) {
                    $optionText = $row["o{$i}"];
                    if (! empty($optionText)) {
                        CbtOption::create([
                            'cbt_question_id' => $question->id,
                            'option_text' => $optionText,
                            'is_correct' => in_array((string) $i, $correctIndices),
                        ]);
                    }
                }

                $count++;
            }
        });

        fclose($handle);

        return [
            'success' => true,
            'count' => $count,
            'errors' => $errors,
        ];
    }
}
