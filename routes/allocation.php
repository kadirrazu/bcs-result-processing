<?php
use App\Http\Controllers\AllocationController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])->prefix('allocation')->name('allocation.')->group(function(){
    Route::get('/',[AllocationController::class,'index'])->name('index');
    Route::post('/settings/finalize',[AllocationController::class,'finalizeSettings'])->name('settings.finalize');
    Route::post('/input-freeze/freeze',[AllocationController::class,'freezeInputs'])->name('input-freeze.freeze');
    Route::get('/input-freeze/status',[AllocationController::class,'inputFreezeStatus'])->name('input-freeze.status');
    Route::get('/input-freeze/{freeze}',[AllocationController::class,'showInputFreeze'])->name('input-freeze.show');
    Route::get('/input-freeze/{freeze}/cadre/{circularEntry}/queue',[AllocationController::class,'showCadreQueue'])->name('input-freeze.cadre-queue');
    Route::post('/phase-one/start',[AllocationController::class,'startPhaseOne'])->name('phase-one.start');
    Route::get('/phase-one/status',[AllocationController::class,'phaseOneStatus'])->name('phase-one.status');
    Route::get('/runs/{run}',[AllocationController::class,'showRun'])->name('runs.show');
    Route::post('/runs/{run}/a4/start',[AllocationController::class,'startA4'])->name('a4.start');
    Route::get('/a4/runs/{a4Run}/status',[AllocationController::class,'a4Status'])->name('a4.status');
    Route::get('/a4/runs/{a4Run}/processing',[AllocationController::class,'showA4Processing'])->name('a4.processing');
    Route::get('/a4/runs/{a4Run}',[AllocationController::class,'showA4'])->name('a4.show');
    Route::get('/seat-breakup/template',[AllocationController::class,'seatTemplate'])->name('seat-breakup.template');
    Route::post('/seat-breakup/upload',[AllocationController::class,'uploadSeatBreakup'])->name('seat-breakup.upload');
    Route::get('/seat-breakup/{version}',[AllocationController::class,'showSeatBreakup'])->name('seat-breakup.show');
    Route::get('/seat-breakup/{version}/pdf',[AllocationController::class,'seatBreakupPdf'])->name('seat-breakup.pdf');
    Route::post('/seat-breakup/{version}/finalize',[AllocationController::class,'finalizeSeatBreakup'])->name('seat-breakup.finalize');
});
