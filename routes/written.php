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
        Route::get('/import/{batch}/corrections/template', [WrittenController::class, 'correctionTemplate'])->name('import.corrections.template');
        Route::post('/import/{batch}/corrections', [WrittenController::class, 'applyCorrections'])->name('import.corrections.store');
        Route::post('/import/{batch}/retry-staging', [WrittenController::class, 'retryStaging'])->name('import.retry-staging');
        Route::post('/import/{batch}/validate', [WrittenController::class, 'validateBatch'])->name('import.validate');
        Route::post('/import/{batch}/approve', [WrittenController::class, 'approve'])->name('import.approve');
        Route::get('/import/{batch}/report', [WrittenController::class, 'report'])->name('import.report');
        Route::post('/reconciliation/generate', [WrittenController::class, 'generateReconciliation'])->name('reconciliation.generate');
        Route::get('/reconciliation', [WrittenController::class, 'reconciliation'])->name('reconciliation');
        Route::post('/rules/process', [WrittenController::class, 'processRules'])->name('rules.process');
        Route::get('/rules/run/{run}/status', [WrittenController::class, 'processingRunStatus'])->name('rules.status');
        Route::get('/processing/run/{run}/status', [WrittenController::class, 'processingRunStatus'])->name('processing.status');
        Route::post('/finalize', [WrittenController::class, 'finalize'])->name('finalize');
        Route::get('/final-result/combined', [WrittenController::class, 'finalResultCombined'])->name('final-result.combined');
        Route::get('/final-result/category', [WrittenController::class, 'finalResultCategory'])->name('final-result.category');
        Route::get('/final-result/combined.txt', [WrittenController::class, 'finalResultCombinedTxt'])->name('final-result.combined.txt');
        Route::get('/final-result/category.txt', [WrittenController::class, 'finalResultCategoryTxt'])->name('final-result.category.txt');
        Route::get('/final-result/fill-template', [WrittenController::class, 'finalResultTemplate'])->name('final-result.template');
        Route::post('/final-result/fill-template', [WrittenController::class, 'generateFinalResultTemplate'])->name('final-result.template.generate');
        Route::get('/failure-reasons', [WrittenController::class, 'failureReasons'])->name('failure-reasons');
        Route::get('/paper-crashes', [WrittenController::class, 'paperCrashes'])->name('paper-crashes');
        Route::get('/paper-crashes/export.xlsx', [WrittenController::class, 'paperCrashesXlsx'])->name('paper-crashes.xlsx');
        Route::get('/paper-crashes/export.csv', [WrittenController::class, 'paperCrashesCsv'])->name('paper-crashes.csv');
        Route::get('/high-mark-review', [WrittenController::class, 'highMarks'])->name('high-marks');
        Route::get('/high-mark-review/export.xlsx', [WrittenController::class, 'highMarksXlsx'])->name('high-marks.xlsx');
        Route::get('/high-mark-review/export.csv', [WrittenController::class, 'highMarksCsv'])->name('high-marks.csv');
        Route::get('/exports/xlsx', [WrittenController::class, 'administrativeExportXlsx'])->name('exports.xlsx');
        Route::get('/results', [WrittenController::class, 'results'])->name('results');
        Route::get('/results/{result}', [WrittenController::class, 'show'])->name('results.show');
        Route::get('/results/{result}/edit', [WrittenController::class, 'edit'])->name('results.edit');
        Route::put('/results/{result}', [WrittenController::class, 'update'])->name('results.update');
    });
