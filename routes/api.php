<?php

use Illimi\Gradebook\Controllers\V1\AssessmentController;
use Illimi\Gradebook\Controllers\V1\AssessmentTemplateController;
use Illimi\Gradebook\Controllers\V1\GradebookContextController;
use Illimi\Gradebook\Controllers\V1\HealthController;
use Illimi\Gradebook\Controllers\V1\ReportController;
use Illimi\Gradebook\Controllers\V1\StudentRatingController;
use Illimi\Gradebook\Controllers\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/gradebook')
    ->name('v1.gradebook.')
    ->middleware(['api', 'auth:sanctum', 'organization', 'core.role:admin|super-admin|principal|teacher'])
    ->group(function () {
    Route::get('context/dashboard', [GradebookContextController::class, 'dashboard'])
        ->middleware('throttle:60,1')
        ->name('context.dashboard');
    Route::get('context/assessments', [GradebookContextController::class, 'assessmentsIndex'])
        ->middleware('throttle:60,1')
        ->name('context.assessments');
    Route::get('context/sheet', [GradebookContextController::class, 'sheet'])
        ->middleware('throttle:60,1')
        ->name('context.sheet');
    Route::get('context/ratings', [GradebookContextController::class, 'ratings'])
        ->middleware('throttle:60,1')
        ->name('context.ratings');
    Route::get('context/reports', [GradebookContextController::class, 'reports'])
        ->middleware('throttle:60,1')
        ->name('context.reports');
    Route::get('context/tokens', [GradebookContextController::class, 'tokens'])
        ->middleware('throttle:60,1')
        ->name('context.tokens');
    Route::get('context/templates', [GradebookContextController::class, 'templates'])
        ->middleware('throttle:60,1')
        ->name('context.templates');

    Route::apiResource('templates', AssessmentTemplateController::class);
    Route::apiResource('assessments', AssessmentController::class);
    Route::post('student-ratings', [StudentRatingController::class, 'store'])->name('student_ratings.store');
    Route::apiResource('reports', ReportController::class);
    Route::post('reports/generate', [ReportController::class, 'generate'])->name('reports.generate');
    Route::apiResource('tokens', TokenController::class);
    Route::post('tokens/generate', [TokenController::class, 'generate'])->name('tokens.generate');
    Route::get('health', [HealthController::class, 'summary'])->name('health.summary');
    Route::get('alerts', [HealthController::class, 'alerts'])->name('alerts.index');
    Route::post('health/run', [HealthController::class, 'run'])->name('health.run');
    Route::patch('alerts/{id}/resolve', [HealthController::class, 'resolve'])->name('alerts.resolve');
    Route::post('alerts/bulk-resolve', [HealthController::class, 'bulkResolve'])->name('alerts.bulk_resolve');
});
