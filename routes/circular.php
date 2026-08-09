<?php

use App\Http\Controllers\CircularController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('circular')->name('circular.')->group(function (): void {
        Route::get('/', [CircularController::class, 'index'])->name('index');
        Route::get('/view', [CircularController::class, 'view'])->name('view');
        Route::get('/template', [CircularController::class, 'template'])->name('template');
        Route::post('/import', [CircularController::class, 'upload'])->name('import.upload');
        Route::get('/import/{batch}', [CircularController::class, 'review'])->name('import.review');
        Route::post('/import/{batch}/approve', [CircularController::class, 'approve'])->name('import.approve');
        Route::get('/entries/create', [CircularController::class, 'create'])->name('entries.create');
        Route::post('/entries', [CircularController::class, 'store'])->name('entries.store');
        Route::get('/entries/{entry}/edit', [CircularController::class, 'edit'])->name('entries.edit');
        Route::put('/entries/{entry}', [CircularController::class, 'update'])->name('entries.update');
        Route::delete('/entries/{entry}', [CircularController::class, 'destroy'])->name('entries.destroy');
    });
