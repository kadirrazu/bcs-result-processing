<?php

use App\Http\Controllers\Reporting\ReportingController;
use App\Http\Controllers\Reporting\CadreSectionReportingController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('reports')->name('examination-reports.')->group(function (): void {
        Route::get('/', [ReportingController::class, 'index'])->name('index');
        Route::get('/cadre-section', [CadreSectionReportingController::class, 'index'])->name('cadre.index');
        Route::get('/cadre-section/verification/{type}', [CadreSectionReportingController::class, 'verification'])
            ->whereIn('type', ['common','general','technical-only'])->name('cadre.verification');
        Route::get('/cadre-section/verification/technical-cadre/{cadreCode}', [CadreSectionReportingController::class, 'technicalCadre'])
            ->whereNumber('cadreCode')->name('cadre.verification.technical');
    });
