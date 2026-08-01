<?php

use App\Http\Controllers\PreliminaryController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('preliminary')->name('preliminary.')
    ->group(function (): void {
        Route::get('/', [PreliminaryController::class, 'index'])->name('index');
        Route::get('/template', [PreliminaryController::class, 'template'])->name('template');
        Route::post('/import', [PreliminaryController::class, 'store'])->name('import.store');
        Route::get('/import/{batch}/result', [PreliminaryController::class, 'result'])->name('import.result');
        Route::get('/import/{batch}/status', [PreliminaryController::class, 'status'])->name('import.status');
        Route::post('/import/{batch}/validate', [PreliminaryController::class, 'validateBatch'])->name('import.validate');
        Route::post('/import/{batch}/approve', [PreliminaryController::class, 'approve'])->name('import.approve');
        Route::get('/import/{batch}/report', [PreliminaryController::class, 'report'])->name('import.report');

        Route::post('/reconciliation/generate', [PreliminaryController::class, 'generateReconciliation'])->name('reconciliation.generate');
        Route::get('/reconciliation/{report}', [PreliminaryController::class, 'reconciliation'])->name('reconciliation.show');
        Route::get('/reconciliation/{report}/csv/{group}', [PreliminaryController::class, 'reconciliationCsv'])->name('reconciliation.csv');

        Route::post('/distribution/generate', [PreliminaryController::class, 'generateDistribution'])->name('distribution.generate');
        Route::get('/distribution/{report}', [PreliminaryController::class, 'distribution'])->name('distribution.show');
        Route::get('/distribution/{report}/csv', [PreliminaryController::class, 'distributionCsv'])->name('distribution.csv');
        Route::post('/cutoff/propose', [PreliminaryController::class, 'proposeCutoff'])->name('cutoff.propose');
        Route::post('/cutoff/{decision}/approve', [PreliminaryController::class, 'approveCutoff'])->name('cutoff.approve');

        Route::post('/finalization', [PreliminaryController::class, 'finalizeResults'])->name('finalization.store');
        Route::get('/finalization/{run}/status', [PreliminaryController::class, 'finalizationStatus'])->name('finalization.status');
        Route::get('/final-result/combined', [PreliminaryController::class, 'finalResultCombined'])->name('final-result.combined');
        Route::get('/final-result/category-wise', [PreliminaryController::class, 'finalResultCategory'])->name('final-result.category');
        Route::get('/final-result/combined.txt', [PreliminaryController::class, 'finalResultCombinedTxt'])->name('final-result.combined.txt');
        Route::get('/final-result/category-wise.txt', [PreliminaryController::class, 'finalResultCategoryTxt'])->name('final-result.category.txt');

        Route::get('/results', [PreliminaryController::class, 'results'])->name('results.index');
        Route::get('/results/{result}/edit', [PreliminaryController::class, 'edit'])->name('results.edit');
        Route::put('/results/{result}', [PreliminaryController::class, 'update'])->name('results.update');
    });
