<?php
use App\Http\Controllers\AllocationController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])->prefix('allocation')->name('allocation.')->group(function(){
    Route::get('/',[AllocationController::class,'index'])->name('index');
    Route::post('/settings/finalize',[AllocationController::class,'finalizeSettings'])->name('settings.finalize');
    Route::get('/seat-breakup/template',[AllocationController::class,'seatTemplate'])->name('seat-breakup.template');
    Route::post('/seat-breakup/upload',[AllocationController::class,'uploadSeatBreakup'])->name('seat-breakup.upload');
    Route::get('/seat-breakup/{version}',[AllocationController::class,'showSeatBreakup'])->name('seat-breakup.show');
    Route::get('/seat-breakup/{version}/pdf',[AllocationController::class,'seatBreakupPdf'])->name('seat-breakup.pdf');
    Route::post('/seat-breakup/{version}/finalize',[AllocationController::class,'finalizeSeatBreakup'])->name('seat-breakup.finalize');
});
