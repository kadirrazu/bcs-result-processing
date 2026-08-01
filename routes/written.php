<?php

use App\Http\Controllers\WrittenController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('written')->name('written.')
    ->group(function (): void {
        Route::get('/', [WrittenController::class, 'index'])->name('index');
        Route::get('/template', [WrittenController::class, 'template'])->name('template');
        Route::post('/import', [WrittenController::class, 'store'])->name('import.store');
        Route::get('/import/{batch}/result', [WrittenController::class, 'result'])->name('import.result');
        Route::get('/import/{batch}/status', [WrittenController::class, 'status'])->name('import.status');
        Route::post('/import/{batch}/retry-staging', [WrittenController::class, 'retryStaging'])->name('import.retry-staging');
        Route::post('/import/{batch}/validate', [WrittenController::class, 'validateBatch'])->name('import.validate');
        Route::post('/import/{batch}/approve', [WrittenController::class, 'approve'])->name('import.approve');
        Route::get('/import/{batch}/report', [WrittenController::class, 'report'])->name('import.report');
        Route::post('/reconciliation/generate', [WrittenController::class, 'generateReconciliation'])->name('reconciliation.generate');
        Route::get('/reconciliation', [WrittenController::class, 'reconciliation'])->name('reconciliation');
        Route::post('/rules/process', [WrittenController::class, 'processRules'])->name('rules.process');
        Route::get('/rules/run/{run}/status', [WrittenController::class, 'processingRunStatus'])->name('rules.status');
        Route::get('/paper-crashes', [WrittenController::class, 'paperCrashes'])->name('paper-crashes');
        Route::get('/results', [WrittenController::class, 'results'])->name('results');
    });
