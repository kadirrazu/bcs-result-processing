<?php

use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {

    Route::view('/dashboard', 'dashboard.index')
        ->name('dashboard');

    Route::resource('users', UserController::class)
        ->except('destroy');

    require __DIR__.'/examinations.php';

    require __DIR__.'/master-data.php';

    require __DIR__.'/registration-masters.php';

    require __DIR__.'/registrations.php';

    require __DIR__.'/preliminary.php';

    require __DIR__.'/written.php';

    require __DIR__.'/viva.php';

});
