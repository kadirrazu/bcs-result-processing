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
    });
