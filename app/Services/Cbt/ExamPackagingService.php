<?php

namespace App\Services\Cbt;

use App\Models\CbtExam;
use App\Models\CourseRegistration;
use App\Models\StudentCbtProfile;
use ZipArchive;

class ExamPackagingService
{
    /**
     * Create a secure ZIP package for an exam.
     */
    public function createPackage(CbtExam $exam, ?array $studentIds = null): array
    {
        $exam->load(['questions.options', 'academicSession', 'semester', 'institution', 'course']);

        // 1. Prepare JSON Data
        $data = [
            'exam' => [
                'uuid' => $exam->uuid,
                'institution_id' => $exam->institution_id,
                'title' => $exam->course->title,
                'course_code' => $exam->course->course_code,
                'level' => $exam->course->level,
                'dept' => $exam->course->department_id,
                'session' => $exam->academicSession->name,
                'semester' => $exam->semester->name,
                'exam_date' => $exam->exam_date?->format('Y-m-d'),
                'duration' => $exam->duration_minutes,
                'total_questions' => $exam->total_questions,
                'pass_mark' => $exam->pass_mark,
                'randomize' => $exam->randomize_questions,
                'randomize_options' => $exam->randomize_options,
            ],
            'questions' => $exam->questions->map(fn ($q) => [
                'uuid' => $q->uuid,
                'text' => $q->question_text,
                'type' => $q->type,
                'marks' => $q->marks,
                'media' => $q->media_path,
                'options' => $q->options->map(fn ($o) => [
                    'uuid' => $o->uuid,
                    'text' => $o->option_text,
                    'is_correct' => $o->is_correct,
                ]),
            ]),
            'students' => $this->getEligibleStudents($exam, $studentIds),
        ];

        $jsonContent = json_encode($data, JSON_PRETTY_PRINT);

        // 2. Prepare ZIP
        $zipName = "exam_{$exam->uuid}.zip";
        $zipPath = storage_path("app/exams/{$zipName}");

        if (! is_dir(dirname($zipPath))) {
            mkdir(dirname($zipPath), 0755, true);
        }

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            // Add JSON
            $zip->addFromString('manifest.json', $jsonContent);

            // Add Media
            foreach ($exam->questions as $question) {
                if ($question->media_path) {
                    $fullMediaPath = storage_path("app/public/{$question->media_path}");
                    if (file_exists($fullMediaPath)) {
                        $zip->addFile($fullMediaPath, 'media/'.basename($question->media_path));
                    }
                }
            }

            $zip->close();
        } else {
            return ['success' => false, 'message' => 'Could not create ZIP file.'];
        }

        return [
            'success' => true,
            'path' => $zipPath,
            'filename' => $zipName,
        ];
    }

    /**
     * Get students registered for the course in the given session/semester with their PINs.
     */
    private function getEligibleStudents(CbtExam $exam, ?array $studentIds = null): array
    {
        $query = CourseRegistration::where('course_id', $exam->course_id)
            ->where('academic_session_id', $exam->academic_session_id)
            ->where('semester_id', $exam->semester_id)
            ->where('status', 'registered');

        if ($studentIds) {
            $query->whereIn('student_id', $studentIds);
        }

        return $query->with(['student' => function ($q) use ($exam) {
            $q->with(['cbtProfiles' => function ($pq) use ($exam) {
                $pq->where('academic_session_id', $exam->academic_session_id)
                    ->where('semester_id', $exam->semester_id);
            }]);
        }])
            ->get()
            ->map(function ($reg) use ($exam) {
                $student = $reg->student;
                $profile = $student->cbtProfiles->first();

                if (! $profile) {
                    $profile = StudentCbtProfile::firstOrCreate([
                        'student_id' => $student->id,
                        'academic_session_id' => $exam->academic_session_id,
                        'semester_id' => $exam->semester_id,
                    ], [
                        'cbt_pin' => (string) random_int(111111, 999999),
                        'last_generated_at' => now(),
                    ]);
                }

                return [
                    'matric_no' => $student->matric_number,
                    'name' => $student->full_name,
                    'pin' => $profile->cbt_pin,
                ];
            })
            ->toArray();
    }
}
