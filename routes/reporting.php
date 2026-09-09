<?php

use App\Http\Controllers\Reporting\CadreSectionReportingController;
use App\Http\Controllers\Reporting\ReportingController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('reports')->name('examination-reports.')->group(function (): void {
        Route::get('/', [ReportingController::class, 'index'])->name('index');
        Route::get('/cadre-section', [CadreSectionReportingController::class, 'index'])->name('cadre.index');

        Route::get('/cadre-section/verification/general-cadre-wise', [CadreSectionReportingController::class, 'generalCadreWiseIndex'])
            ->name('cadre.verification.general-cadre-wise');
        Route::get('/cadre-section/verification/technical-cadre-wise', [CadreSectionReportingController::class, 'technicalCadreWiseIndex'])
            ->name('cadre.verification.technical-cadre-wise');

        Route::get('/cadre-section/verification/cadre-serial-merit-report', [CadreSectionReportingController::class, 'cadreSerialMerit'])
            ->name('cadre.verification.cadre-serial-merit');
        Route::post('/cadre-section/verification/cadre-serial-merit-report/pdf', [CadreSectionReportingController::class, 'queueCadreSerialMeritPdf'])
            ->name('cadre.verification.cadre-serial-merit.pdf');

        Route::get('/cadre-section/verification/general-cadre/{cadreCode}', [CadreSectionReportingController::class, 'generalCadre'])
            ->whereNumber('cadreCode')->name('cadre.verification.general-cadre');
        Route::post('/cadre-section/verification/general-cadre/{cadreCode}/pdf', [CadreSectionReportingController::class, 'queueGeneralCadrePdf'])
            ->whereNumber('cadreCode')->name('cadre.verification.general-cadre.pdf');

        Route::get('/cadre-section/verification/{type}', [CadreSectionReportingController::class, 'verification'])
            ->whereIn('type', ['common','general','technical-only','quota'])->name('cadre.verification');
        Route::post('/cadre-section/verification/{type}/pdf', [CadreSectionReportingController::class, 'queueVerificationPdf'])
            ->whereIn('type', ['common','general','technical-only','quota'])->name('cadre.verification.pdf');

        Route::get('/cadre-section/verification/technical-cadre/{cadreCode}', [CadreSectionReportingController::class, 'technicalCadre'])
            ->whereNumber('cadreCode')->name('cadre.verification.technical');
        Route::post('/cadre-section/verification/technical-cadre/{cadreCode}/pdf', [CadreSectionReportingController::class, 'queueTechnicalCadrePdf'])
            ->whereNumber('cadreCode')->name('cadre.verification.technical.pdf');

        Route::get('/cadre-section/verification/exports/{exportRun}', [CadreSectionReportingController::class, 'exportRun'])
            ->name('cadre.verification.exports.show');
        Route::get('/cadre-section/verification/exports/{exportRun}/status', [CadreSectionReportingController::class, 'exportStatus'])
            ->name('cadre.verification.exports.status');
        Route::get('/cadre-section/verification/exports/{exportRun}/download', [CadreSectionReportingController::class, 'download'])
            ->name('cadre.verification.exports.download');
    });
