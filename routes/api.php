<?php

use App\Http\Controllers\Api\CbtSyncController;
use App\Models\Program;
use App\Models\Staff;
use App\Models\Student;
use App\Models\StudentPlacement;
use App\Models\SystemSetting;
use App\Models\WebsiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::prefix('v1/cbt')->group(function () {
        Route::get('/exams', [CbtSyncController::class, 'index']);
        Route::get('/package/{uuid}', [CbtSyncController::class, 'downloadPackage']);
        Route::post('/results', [CbtSyncController::class, 'ingestResults']);
    });
});

Route::prefix('public')->group(function () {
    Route::get('/website-settings', function () {
        $settings = WebsiteSetting::pluck('value', 'key')->toArray();
        $systemLogo = SystemSetting::where('key', 'system_logo')->value('value');

        if ($systemLogo) {
            $settings['system_logo'] = 'data:image/png;base64,'.$systemLogo;
        }

        if (empty($settings['website_name'])) {
            $settings['website_name'] = config('app.name');
        }

        $settings['theme'] = config('theme');

        return response()->json($settings);
    });

    Route::get('/stats', function () {
        return response()->json([
            'students' => Student::count() ?: 1200,
            'programs' => Program::count() ?: 45,
            'staff' => Staff::count() ?: 120,
            'placements' => StudentPlacement::count() ?: 850,
        ]);
    });

    Route::get('/programs', function () {
        return response()->json(
            Program::with('department.institution')->get()->map(function ($program) {
                return [
                    'id' => $program->id,
                    'name' => $program->name,
                    'code' => $program->code,
                    'type' => $program->program_type,
                    'department' => $program->department?->name,
                    'institution' => $program->department?->institution?->name,
                ];
            })
        );
    });
});
