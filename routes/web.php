<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\UserController;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {
    Route::view('/dashboard', 'dashboard.index')
        ->name('dashboard');

    Route::resource('users', UserController::class)
        ->except('destroy');
});
