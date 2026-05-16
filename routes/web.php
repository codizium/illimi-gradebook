<?php

use Illimi\Gradebook\Controllers\Web\GradebookWebController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth'])
    ->prefix('gradebook')
    ->name('gradebook.')
    ->middleware('core.role:admin|super-admin|principal|teacher')
    ->group(function () {
        Route::get('/', [GradebookWebController::class, 'dashboard'])->name('index');
        Route::get('/assessments', [GradebookWebController::class, 'assessments'])->name('assessments.index');
        Route::get('/templates', [GradebookWebController::class, 'templates'])->name('templates.index');
        Route::get('/reports', [GradebookWebController::class, 'reports'])->name('reports.index');
        Route::get('/tokens/export', [GradebookWebController::class, 'exportTokens'])->name('tokens.export');
        Route::get('/tokens/{token}/download', [GradebookWebController::class, 'downloadToken'])->name('tokens.download');
        Route::get('/tokens', [GradebookWebController::class, 'tokens'])->name('tokens.index');
        Route::get('/subjects/{subject}/classes/{class}', [GradebookWebController::class, 'show'])->name('assessments.show');
        Route::get('/classes/{class}/effective-assessment', [GradebookWebController::class, 'effectiveAssessment'])->name('ratings.effective');
        Route::get('/classes/{class}/psychomotor-assessment', [GradebookWebController::class, 'psychomotorAssessment'])->name('ratings.psychomotor');
    });
