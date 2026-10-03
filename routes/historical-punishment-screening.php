<?php

use App\Http\Controllers\HistoricalPunishmentController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('historical-punishment-screening')
    ->name('historical-punishments.screening.')
    ->group(function (): void {
        Route::get('/', [HistoricalPunishmentController::class, 'screening'])->name('index');
        Route::post('/run', [HistoricalPunishmentController::class, 'run'])->name('run');
        Route::get('/runs/{run}', [HistoricalPunishmentController::class, 'showScreening'])->name('show');
        Route::get('/runs/{run}/status', [HistoricalPunishmentController::class, 'screeningStatus'])->name('status');
        Route::post('/matches/{match}/review', [HistoricalPunishmentController::class, 'review'])->name('review');
        Route::get('/runs/{run}/xlsx', [HistoricalPunishmentController::class, 'export'])->name('export');
    });
