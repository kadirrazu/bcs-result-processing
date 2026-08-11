<?php

use App\Http\Controllers\ChoiceValidationController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('choice-validation')
    ->name('choice-validation.')
    ->group(function (): void {
        Route::get('/', [ChoiceValidationController::class, 'index'])->name('index');
        Route::get('/template', [ChoiceValidationController::class, 'template'])->name('template');
        Route::post('/import', [ChoiceValidationController::class, 'upload'])->name('import.upload');
        Route::get('/import/{batch}', [ChoiceValidationController::class, 'show'])->whereNumber('batch')->name('import.show');
        Route::get('/import/{batch}/status', [ChoiceValidationController::class, 'importStatus'])->whereNumber('batch')->name('import.status');
        Route::post('/import/{batch}/validate', [ChoiceValidationController::class, 'validateSource'])->whereNumber('batch')->name('import.validate');
        Route::post('/import/{batch}/approve', [ChoiceValidationController::class, 'approve'])->whereNumber('batch')->name('import.approve');
        Route::get('/import/{batch}/invalid-rows', [ChoiceValidationController::class, 'invalidRows'])->whereNumber('batch')->name('import.invalid-rows');
        Route::post('/import/{batch}/invalid-rows', [ChoiceValidationController::class, 'correctInvalidRows'])->whereNumber('batch')->name('import.correct-invalid');
        Route::post('/process', [ChoiceValidationController::class, 'processChoices'])->name('process');
        Route::get('/finalization', [ChoiceValidationController::class, 'finalization'])->name('finalization.index');
        Route::post('/finalization', [ChoiceValidationController::class, 'finalizeValidation'])->name('finalization.finalize');
        Route::get('/final-report', [ChoiceValidationController::class, 'finalReport'])->name('final-report.index');
        Route::get('/final-report/pdf', [ChoiceValidationController::class, 'finalReportPdf'])->name('final-report.pdf');
        Route::get('/final-report/excel', [ChoiceValidationController::class, 'finalReportExcel'])->name('final-report.excel');
        Route::get('/results/{run?}', [ChoiceValidationController::class, 'results'])->whereNumber('run')->name('results');
        Route::get('/runs/{run}/progress', [ChoiceValidationController::class, 'validationProgress'])->whereNumber('run')->name('runs.progress');
        Route::get('/result/{result}', [ChoiceValidationController::class, 'resultDetail'])->whereNumber('result')->name('result.detail');
        Route::post('/result/{result}/correct', [ChoiceValidationController::class, 'correctResult'])->whereNumber('result')->name('result.correct');
    });
