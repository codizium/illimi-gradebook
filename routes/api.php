<?php

use Illimi\Gradebook\Controllers\V1\AssessmentController;
use Illimi\Gradebook\Controllers\V1\AssessmentTemplateController;
use Illimi\Gradebook\Controllers\V1\ReportController;
use Illimi\Gradebook\Controllers\V1\StudentRatingController;
use Illimi\Gradebook\Controllers\V1\TokenController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/v1/gradebook')->middleware(['api', 'auth:sanctum'])->group(function () {
    Route::get('templates', [AssessmentTemplateController::class, 'index'])->name('v1.gradebook.templates.index');
    Route::post('templates', [AssessmentTemplateController::class, 'store'])->name('v1.gradebook.templates.store');
    Route::get('templates/{id}', [AssessmentTemplateController::class, 'show'])->name('v1.gradebook.templates.show');
    Route::put('templates/{id}', [AssessmentTemplateController::class, 'update'])->name('v1.gradebook.templates.update');
    Route::delete('templates/{id}', [AssessmentTemplateController::class, 'destroy'])->name('v1.gradebook.templates.destroy');

    Route::get('assessments', [AssessmentController::class, 'index'])->name('v1.gradebook.assessments.index');
    Route::post('assessments', [AssessmentController::class, 'store'])->name('v1.gradebook.assessments.store');
    Route::get('assessments/{id}', [AssessmentController::class, 'show'])->name('v1.gradebook.assessments.show');
    Route::put('assessments/{id}', [AssessmentController::class, 'update'])->name('v1.gradebook.assessments.update');
    Route::delete('assessments/{id}', [AssessmentController::class, 'destroy'])->name('v1.gradebook.assessments.destroy');
    Route::post('student-ratings', [StudentRatingController::class, 'store'])->name('v1.gradebook.student_ratings.store');

    Route::get('reports', [ReportController::class, 'index'])->name('v1.gradebook.reports.index');
    Route::post('reports', [ReportController::class, 'store'])->name('v1.gradebook.reports.store');
    Route::get('reports/{id}', [ReportController::class, 'show'])->name('v1.gradebook.reports.show');
    Route::put('reports/{id}', [ReportController::class, 'update'])->name('v1.gradebook.reports.update');
    Route::delete('reports/{id}', [ReportController::class, 'destroy'])->name('v1.gradebook.reports.destroy');
    Route::post('reports/generate', [ReportController::class, 'generate'])->name('v1.gradebook.reports.generate');

    Route::get('tokens', [TokenController::class, 'index'])->name('v1.gradebook.tokens.index');
    Route::post('tokens', [TokenController::class, 'store'])->name('v1.gradebook.tokens.store');
    Route::post('tokens/generate', [TokenController::class, 'generate'])->name('v1.gradebook.tokens.generate');
    Route::get('tokens/{id}', [TokenController::class, 'show'])->name('v1.gradebook.tokens.show');
    Route::put('tokens/{id}', [TokenController::class, 'update'])->name('v1.gradebook.tokens.update');
    Route::delete('tokens/{id}', [TokenController::class, 'destroy'])->name('v1.gradebook.tokens.destroy');
});
