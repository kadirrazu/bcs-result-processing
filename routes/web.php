<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\UserAppearanceController;
use App\Http\Middleware\EnsureExaminationProcessingOpen;
use App\Http\Middleware\ConfigureSelectedExaminationConnection;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('auth')->group(function () {

    Route::view('/dashboard', 'dashboard.index')
        ->middleware(ConfigureSelectedExaminationConnection::class)
        ->name('dashboard');

    Route::resource('users', UserController::class)
        ->except('destroy');

    Route::get('/settings/appearance', [UserAppearanceController::class, 'edit'])->name('settings.appearance.edit');
    Route::put('/settings/appearance', [UserAppearanceController::class, 'update'])->name('settings.appearance.update');
    Route::delete('/settings/appearance', [UserAppearanceController::class, 'reset'])->name('settings.appearance.reset');

    require __DIR__.'/examinations.php';

    require __DIR__.'/master-data.php';

    require __DIR__.'/previous-bcs-repository.php';

    require __DIR__.'/historical-punishments.php';

    require __DIR__.'/registration-masters.php';

    Route::middleware(EnsureExaminationProcessingOpen::class)->group(function (): void {
        require __DIR__.'/historical-punishment-screening.php';
        require __DIR__.'/overview.php';
        require __DIR__.'/registrations.php';
        require __DIR__.'/preliminary.php';
        require __DIR__.'/written.php';
        require __DIR__.'/viva.php';
        require __DIR__.'/circular.php';
        require __DIR__.'/choice-validation.php';
        require __DIR__.'/tabulation.php';
        require __DIR__.'/merit.php';
        require __DIR__.'/choice-optimization.php';
        require __DIR__.'/allocation.php';
        require __DIR__.'/reporting.php';
        require __DIR__.'/non-cadre.php';
    });

});
