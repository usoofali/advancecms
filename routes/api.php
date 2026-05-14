<?php

use App\Http\Controllers\Api\CbtSyncController;
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
