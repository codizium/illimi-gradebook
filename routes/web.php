<?php

use Illimi\Gradebook\Controllers\Web\GradebookWebController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'organization'])
    ->prefix('gradebook')
    ->name('gradebook.')
    ->group(function () {
        Route::get('/', [GradebookWebController::class, 'index'])->name('index');
        Route::get('/assessments', [GradebookWebController::class, 'index'])->name('assessments.index');
        Route::get('/templates', [GradebookWebController::class, 'templates'])->name('templates.index');
        Route::get('/reports', [GradebookWebController::class, 'reports'])->name('reports.index');
        Route::get('/tokens', [GradebookWebController::class, 'tokens'])->name('tokens.index');
        Route::get('/subjects/{subject}/classes/{class}', [GradebookWebController::class, 'show'])->name('assessments.show');
        Route::get('/classes/{class}/effective-assessment', [GradebookWebController::class, 'effectiveAssessment'])->name('ratings.effective');
        Route::get('/classes/{class}/psychomotor-assessment', [GradebookWebController::class, 'psychomotorAssessment'])->name('ratings.psychomotor');
    });
