<?php

use App\Http\Controllers\RegistrationController;
use App\Http\Controllers\RegistrationImportController;
use App\Http\Controllers\RegistrationReportController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])->group(function (): void {
    Route::get('registrations/import', [RegistrationImportController::class, 'create'])->name('registrations.import');
    Route::post('registrations/import', [RegistrationImportController::class, 'store'])->name('registrations.import.store');
    Route::get('registrations/import/template', [RegistrationImportController::class, 'template'])->name('registrations.import.template');
    Route::post('registrations/import/{batch}/validate', [RegistrationImportController::class, 'validateBatch'])->name('registrations.import.validate');
    Route::post('registrations/import/{batch}/approve', [RegistrationImportController::class, 'approve'])->name('registrations.import.approve');
    Route::get('registrations/import/{batch}/status', [RegistrationImportController::class, 'status'])->name('registrations.import.status');
    Route::get('registrations/import/{batch}/result', [RegistrationImportController::class, 'result'])->name('registrations.import-result');
    Route::get('registrations/import/{batch}/report', [RegistrationImportController::class, 'report'])->name('registrations.import.report');
    Route::post('registrations/import/{batch}/rollback', [RegistrationImportController::class, 'rollback'])->name('registrations.import.rollback');
    Route::get('registrations/reports/summary', RegistrationReportController::class)->name('registrations.reports.summary');
    Route::resource('registrations', RegistrationController::class)->except('destroy');
});
