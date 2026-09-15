<?php

use App\Http\Controllers\Reporting\CadreSectionReportingController;
use App\Http\Controllers\Reporting\ReportingController;
use App\Http\Controllers\Reporting\ResearchStatisticsController;
use App\Http\Controllers\Reporting\DynamicQueryBuilderController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('reports')->name('examination-reports.')->group(function (): void {
        Route::get('/', [ReportingController::class, 'index'])->name('index');
        Route::get('/research-statistics', [ResearchStatisticsController::class, 'index'])->name('research-statistics.index');
        Route::get('/research-statistics/{report}', [ResearchStatisticsController::class, 'show'])->name('research-statistics.show');
        Route::get('/research-statistics/{report}/pdf', [ResearchStatisticsController::class, 'pdf'])->name('research-statistics.pdf');
        Route::get('/dynamic-query', [DynamicQueryBuilderController::class, 'index'])->name('dynamic-query.index');
        Route::post('/dynamic-query/preview', [DynamicQueryBuilderController::class, 'preview'])->name('dynamic-query.preview');
        Route::post('/dynamic-query/saved', [DynamicQueryBuilderController::class, 'save'])->name('dynamic-query.saved.save');
        Route::get('/dynamic-query/saved/{savedReport}', [DynamicQueryBuilderController::class, 'showSaved'])->whereNumber('savedReport')->name('dynamic-query.saved.show');
        Route::delete('/dynamic-query/saved/{savedReport}', [DynamicQueryBuilderController::class, 'destroySaved'])->whereNumber('savedReport')->name('dynamic-query.saved.destroy');
        Route::get('/dynamic-query/history', [DynamicQueryBuilderController::class, 'history'])->name('dynamic-query.history');
        Route::post('/dynamic-query/export/xlsx', [DynamicQueryBuilderController::class, 'exportXlsx'])->name('dynamic-query.export.xlsx');
        Route::post('/dynamic-query/export/pdf', [DynamicQueryBuilderController::class, 'exportPdf'])->name('dynamic-query.export.pdf');
        Route::get('/dynamic-query/export/{exportRun}/status', [DynamicQueryBuilderController::class, 'exportStatus'])->whereNumber('exportRun')->name('dynamic-query.export.status');
        Route::get('/dynamic-query/export/{exportRun}/download', [DynamicQueryBuilderController::class, 'downloadExport'])->whereNumber('exportRun')->name('dynamic-query.export.download');
        Route::get('/cadre-section', [CadreSectionReportingController::class, 'index'])->name('cadre.index');


        Route::get('/cadre-section/booklet/general-cadre-wise', [CadreSectionReportingController::class, 'bookletGeneralCadreWiseIndex'])
            ->name('cadre.booklet.general-cadre-wise');
        Route::get('/cadre-section/booklet/technical-cadre-wise', [CadreSectionReportingController::class, 'bookletTechnicalCadreWiseIndex'])
            ->name('cadre.booklet.technical-cadre-wise');

        Route::get('/cadre-section/booklet/general-cadre/{cadreCode}', [CadreSectionReportingController::class, 'bookletGeneralCadre'])
            ->whereNumber('cadreCode')->name('cadre.booklet.general-cadre');
        Route::post('/cadre-section/booklet/general-cadre/{cadreCode}/pdf', [CadreSectionReportingController::class, 'queueBookletGeneralCadrePdf'])
            ->whereNumber('cadreCode')->name('cadre.booklet.general-cadre.pdf');

        Route::get('/cadre-section/booklet/technical-cadre/{cadreCode}', [CadreSectionReportingController::class, 'bookletTechnicalCadre'])
            ->whereNumber('cadreCode')->name('cadre.booklet.technical');
        Route::post('/cadre-section/booklet/technical-cadre/{cadreCode}/pdf', [CadreSectionReportingController::class, 'queueBookletTechnicalCadrePdf'])
            ->whereNumber('cadreCode')->name('cadre.booklet.technical.pdf');

        Route::get('/cadre-section/booklet/{type}', [CadreSectionReportingController::class, 'booklet'])
            ->whereIn('type', ['common','general','technical','technical-only','quota'])->name('cadre.booklet');
        Route::post('/cadre-section/booklet/{type}/pdf', [CadreSectionReportingController::class, 'queueBookletPdf'])
            ->whereIn('type', ['common','general','technical','technical-only','quota'])->name('cadre.booklet.pdf');

        Route::get('/cadre-section/booklet/exports/{exportRun}', [CadreSectionReportingController::class, 'exportRun'])
            ->name('cadre.booklet.exports.show');
        Route::get('/cadre-section/booklet/exports/{exportRun}/status', [CadreSectionReportingController::class, 'exportStatus'])
            ->name('cadre.booklet.exports.status');
        Route::get('/cadre-section/booklet/exports/{exportRun}/download', [CadreSectionReportingController::class, 'download'])
            ->name('cadre.booklet.exports.download');

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
            ->whereIn('type', ['common','general','technical','technical-only','quota'])->name('cadre.verification');
        Route::post('/cadre-section/verification/{type}/pdf', [CadreSectionReportingController::class, 'queueVerificationPdf'])
            ->whereIn('type', ['common','general','technical','technical-only','quota'])->name('cadre.verification.pdf');

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
