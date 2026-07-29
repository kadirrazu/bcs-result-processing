<?php
use App\Http\Controllers\{RegistrationController,RegistrationImportController,RegistrationReportController};
use App\Http\Middleware\{ConfigureExaminationConnection,EnsureExaminationSelected};
use Illuminate\Support\Facades\Route;
Route::middleware([EnsureExaminationSelected::class,ConfigureExaminationConnection::class])->group(function(){
 Route::get('registrations/import',[RegistrationImportController::class,'create'])->name('registrations.import');
 Route::post('registrations/import',[RegistrationImportController::class,'store'])->name('registrations.import.store');
 Route::get('registrations/import/template',[RegistrationImportController::class,'template'])->name('registrations.import.template');
 Route::get('registrations/import/{batch}/result',[RegistrationImportController::class,'result'])->name('registrations.import-result');
 Route::get('registrations/reports/summary',RegistrationReportController::class)->name('registrations.reports.summary');
 Route::resource('registrations',RegistrationController::class)->except('destroy');
});
