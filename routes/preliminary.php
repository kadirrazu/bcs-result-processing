<?php

use App\Http\Controllers\PreliminaryController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('preliminary')
    ->name('preliminary.')
    ->group(function (): void {
        Route::get('/', [PreliminaryController::class, 'index'])->name('index');
        Route::get('/template', [PreliminaryController::class, 'template'])->name('template');
        Route::post('/import', [PreliminaryController::class, 'store'])->name('import.store');
        Route::get('/import/{batch}/result', [PreliminaryController::class, 'result'])->name('import.result');
        Route::get('/import/{batch}/status', [PreliminaryController::class, 'status'])->name('import.status');
        Route::post('/import/{batch}/validate', [PreliminaryController::class, 'validateBatch'])->name('import.validate');
        Route::post('/import/{batch}/approve', [PreliminaryController::class, 'approve'])->name('import.approve');
        Route::get('/import/{batch}/report', [PreliminaryController::class, 'report'])->name('import.report');
    });
