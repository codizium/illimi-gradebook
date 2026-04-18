<?php

use Illimi\Gradebook\Controllers\V1\AssessmentController;
use Illimi\Gradebook\Controllers\V1\AssessmentTemplateController;
use Illimi\Gradebook\Controllers\V1\ReportController;
use Illimi\Gradebook\Controllers\V1\StudentRatingController;
use Illimi\Gradebook\Controllers\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/gradebook')
    ->name('v1.gradebook.')
    ->middleware(['api', 'auth:sanctum', 'organization'])
    ->group(function () {
    Route::apiResource('templates', AssessmentTemplateController::class);
    Route::apiResource('assessments', AssessmentController::class);
    Route::post('student-ratings', [StudentRatingController::class, 'store'])->name('student_ratings.store');
    Route::apiResource('reports', ReportController::class);
    Route::post('reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::apiResource('tokens', TokenController::class);
    Route::post('tokens/generate', [TokenController::class, 'generate'])->name('tokens.generate');
});
