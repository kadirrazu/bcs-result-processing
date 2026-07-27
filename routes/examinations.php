<?php

use App\Http\Controllers\CheckExaminationDatabaseController;
use App\Http\Controllers\ExaminationController;
use App\Http\Controllers\SelectExaminationController;
use Illuminate\Support\Facades\Route;

Route::resource('examinations', ExaminationController::class)->except('destroy');
Route::post('examinations/{examination}/check-database', CheckExaminationDatabaseController::class)
    ->name('examinations.check-database');
Route::post('examinations/{examination}/select', SelectExaminationController::class)
    ->name('examinations.select');
