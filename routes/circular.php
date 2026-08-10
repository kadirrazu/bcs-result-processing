<?php

use App\Http\Controllers\CircularController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('circular')->name('circular.')->group(function (): void {
        Route::get('/', [CircularController::class, 'index'])->name('index');
        Route::get('/view', [CircularController::class, 'view'])->name('view');
        Route::get('/history', [CircularController::class, 'history'])->name('history');
        Route::get('/versions/{version}', [CircularController::class, 'version'])->whereNumber('version')->name('versions.show');
        Route::get('/template', [CircularController::class, 'template'])->name('template');
        Route::post('/import', [CircularController::class, 'upload'])->name('import.upload');
        Route::get('/import/{batch}', [CircularController::class, 'review'])->name('import.review');
        Route::post('/import/{batch}/approve', [CircularController::class, 'approve'])->name('import.approve');
        Route::post('/approve-current-draft', [CircularController::class, 'approveDraft'])->name('draft.approve');

        Route::get('/authority', [CircularController::class, 'authority'])->name('authority.index');
        Route::post('/authority/generate', [CircularController::class, 'generateAuthorityPreview'])->name('authority.generate');
        Route::get('/authority/previews/{preview}/download', [CircularController::class, 'downloadAuthorityPreview'])->whereNumber('preview')->name('authority.download');
        Route::post('/authority/previews/{preview}/confirm', [CircularController::class, 'confirmAuthorityPreview'])->whereNumber('preview')->name('authority.confirm');
        Route::post('/authority/finalize', [CircularController::class, 'finalizeCircular'])->name('authority.finalize');

        Route::get('/final-report', [CircularController::class, 'finalReport'])->name('final-report.index');
        Route::get('/final-report/pdf', [CircularController::class, 'finalReportPdf'])->name('final-report.pdf');
        Route::get('/final-report/excel', [CircularController::class, 'finalReportExcel'])->name('final-report.excel');

        Route::get('/entries', [CircularController::class, 'entries'])->name('entries.index');
        Route::get('/entries/create', [CircularController::class, 'create'])->name('entries.create');
        Route::post('/entries', [CircularController::class, 'store'])->name('entries.store');
        Route::get('/entries/{entry}', [CircularController::class, 'show'])->whereNumber('entry')->name('entries.show');
        Route::get('/entries/{entry}/edit', [CircularController::class, 'edit'])->whereNumber('entry')->name('entries.edit');
        Route::put('/entries/{entry}', [CircularController::class, 'update'])->whereNumber('entry')->name('entries.update');
        Route::delete('/entries/{entry}', [CircularController::class, 'destroy'])->whereNumber('entry')->name('entries.destroy');
    });
