<?php

use App\Http\Controllers\ExaminationOverviewController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->get('/overview', ExaminationOverviewController::class)
    ->name('examination-overview.index');
