<?php

use App\Http\Controllers\NonCadre\Circular\NonCadreCircularController;
use App\Http\Controllers\NonCadre\NonCadreController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('non-cadre')->name('non-cadre.')->group(function (): void {
        Route::get('/', [NonCadreController::class, 'index'])->name('index');
        Route::prefix('circular')->name('circular.')->group(function (): void {
            Route::get('/', [NonCadreCircularController::class, 'index'])->name('index');
            Route::get('/template', [NonCadreCircularController::class, 'template'])->name('template');
            Route::post('/import', [NonCadreCircularController::class, 'upload'])->name('import.upload');
            Route::get('/import/{import}/review', [NonCadreCircularController::class, 'review'])->name('import.review');
            Route::post('/import/{import}/approve', [NonCadreCircularController::class, 'approve'])->name('import.approve');
            Route::get('/version/{version}', [NonCadreCircularController::class, 'version'])->name('version');
            Route::get('/version/{version}/pdf', [NonCadreCircularController::class, 'pdf'])->name('version.pdf');
            Route::get('/version/{version}/excel', [NonCadreCircularController::class, 'excel'])->name('version.excel');
            Route::post('/version/{version}/finalize', [NonCadreCircularController::class, 'finalize'])->name('finalize');
        });
    });
