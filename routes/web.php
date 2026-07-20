<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function (): void {
    Route::view('/dashboard', 'dashboard.index')
        ->name('dashboard');
});
