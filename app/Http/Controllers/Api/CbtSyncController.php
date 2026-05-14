<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CbtExam;
use App\Services\Cbt\CbtResultIngestionService;
use App\Services\Cbt\ExamPackagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CbtSyncController extends Controller
{
    public function __construct(
        protected ExamPackagingService $packagingService,
        protected CbtResultIngestionService $ingestionService
    ) {}

    /**
     * List active exams for the authenticated institution.
     */
    public function index(Request $request): JsonResponse
    {
        $institutionId = $request->user()->institution_id;

        $exams = CbtExam::where('institution_id', $institutionId)
            ->where('status', 'active')
            ->with(['course', 'academicSession', 'semester'])
            ->get()
            ->map(fn ($exam) => [
                'uuid' => $exam->uuid,
                'title' => $exam->title,
                'course' => $exam->course->course_code,
                'session' => $exam->academicSession->name,
                'semester' => $exam->semester->name,
                'duration' => $exam->duration_minutes,
                'questions_count' => $exam->total_questions,
            ]);

        return response()->json($exams);
    }

    /**
     * Download the exam package.
     */
    public function downloadPackage(string $uuid): BinaryFileResponse|JsonResponse
    {
        $exam = CbtExam::where('uuid', $uuid)->firstOrFail();

        // Ensure user belongs to the same institution
        if ($exam->institution_id !== auth()->user()->institution_id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $package = $this->packagingService->createPackage($exam);

        if (! $package['success']) {
            return response()->json(['message' => $package['message']], 500);
        }

        return response()->download($package['path'], $package['filename'])->deleteFileAfterSend(false);
    }

    /**
     * Ingest results from the Lab Server.
     */
    public function ingestResults(Request $request): JsonResponse
    {
        $request->validate([
            'exam_uuid' => 'required|string',
            'submission_token' => 'required|string',
            'results' => 'required|array',
        ]);

        $result = $this->ingestionService->ingest($request->all());

        if (! $result['success']) {
            return response()->json(['message' => $result['message']], 400);
        }

        return response()->json($result);
    }
}
