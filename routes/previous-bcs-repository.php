<?php

use App\Http\Controllers\PreviousBcsRepositoryController;
use Illuminate\Support\Facades\Route;

Route::prefix('previous-bcs-repository')
    ->name('previous-bcs-repository.')
    ->group(function (): void {
        Route::get('/', [PreviousBcsRepositoryController::class, 'index'])->name('index');
        Route::get('/search', [PreviousBcsRepositoryController::class, 'search'])->name('search');
        Route::post('/datasets', [PreviousBcsRepositoryController::class, 'store'])->name('datasets.store');
        Route::get('/datasets/{dataset}', [PreviousBcsRepositoryController::class, 'show'])->name('datasets.show');
        Route::get('/datasets/{dataset}/detail', [PreviousBcsRepositoryController::class, 'detail'])->name('datasets.detail');
        Route::get('/datasets/{dataset}/rows/{row}', [PreviousBcsRepositoryController::class, 'rowDetail'])->name('datasets.rows.show');
        Route::get('/datasets/{dataset}/status', [PreviousBcsRepositoryController::class, 'status'])->name('datasets.status');
        Route::post('/datasets/{dataset}/validate', [PreviousBcsRepositoryController::class, 'validateDataset'])->name('datasets.validate');
        Route::post('/datasets/{dataset}/effective', [PreviousBcsRepositoryController::class, 'makeEffective'])->name('datasets.effective');
    });
