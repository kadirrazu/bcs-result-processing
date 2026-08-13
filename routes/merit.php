<?php

use App\Http\Controllers\MeritController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])->prefix('merit')->name('merit.')->group(function () {
    Route::get('/', [MeritController::class, 'index'])->name('index');
    Route::post('/generate', [MeritController::class, 'start'])->name('generate');
    Route::get('/runs/{run}', [MeritController::class, 'runShow'])->name('run.show');
    Route::get('/runs/{run}/status', [MeritController::class, 'runStatus'])->name('run.status');
    Route::get('/results', [MeritController::class, 'results'])->name('results');
    Route::get('/results/{result}', [MeritController::class, 'show'])->name('show');
    Route::get('/results/{result}/pdf', [MeritController::class, 'pdf'])->name('pdf');
    Route::get('/cadres/{cadreCode}', [MeritController::class, 'cadre'])->name('cadre');
    Route::get('/exports/final.xlsx', [MeritController::class, 'exportAll'])->name('export.xlsx');
    Route::get('/cadres/{cadreCode}/export.xlsx', [MeritController::class, 'exportCadre'])->name('cadre.export.xlsx');
    Route::post('/finalizations/{finalization}/rollback', [MeritController::class, 'rollback'])->name('rollback');
    Route::post('/finalize', [MeritController::class, 'finalize'])->name('finalize');
});
