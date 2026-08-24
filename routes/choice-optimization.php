<?php

use App\Http\Controllers\ChoiceOptimizationController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('choice-optimization')
    ->name('choice-optimization.')
    ->group(function (): void {
        Route::get('/', [ChoiceOptimizationController::class, 'index'])->name('index');
        Route::post('/setting', [ChoiceOptimizationController::class, 'updateSetting'])->name('setting.update');

        Route::post('/historical/pull', [ChoiceOptimizationController::class, 'pullHistorical'])->name('historical.pull');
        Route::get('/historical/{source}', [ChoiceOptimizationController::class, 'showHistorical'])->name('historical.show');
        Route::get('/historical/{source}/status', [ChoiceOptimizationController::class, 'historicalStatus'])->name('historical.status');
        Route::get('/historical/{source}/matches/{match}', [ChoiceOptimizationController::class, 'showHistoricalMatch'])->name('historical.matches.show');
        Route::post('/historical/{source}/matches/{match}/resolve', [ChoiceOptimizationController::class, 'resolveHistoricalMatch'])->name('historical.matches.resolve');

        Route::get('/omr/template', [ChoiceOptimizationController::class, 'omrTemplate'])->name('omr.template');
        Route::post('/omr/upload', [ChoiceOptimizationController::class, 'uploadOmr'])->name('omr.upload');
        Route::get('/omr/{batch}', [ChoiceOptimizationController::class, 'showOmr'])->name('omr.show');
        Route::get('/omr/{batch}/row/{row}', [ChoiceOptimizationController::class, 'showOmrRow'])->name('omr.row.show');
        Route::get('/omr/{batch}/status', [ChoiceOptimizationController::class, 'omrStatus'])->name('omr.status');
        Route::post('/omr/{batch}/validate', [ChoiceOptimizationController::class, 'validateOmr'])->name('omr.validate');
        Route::post('/omr/{batch}/revalidate', [ChoiceOptimizationController::class, 'revalidateOmr'])->name('omr.revalidate');
        Route::post('/omr/{batch}/approve', [ChoiceOptimizationController::class, 'approveOmr'])->name('omr.approve');
        Route::post('/omr-row/{row}/resolve-registration', [ChoiceOptimizationController::class, 'resolveOmrRegistration'])->name('omr.resolve-registration');
        Route::post('/omr-row/{row}/resolve-decision', [ChoiceOptimizationController::class, 'resolveOmrDecision'])->name('omr.resolve-decision');
    });
