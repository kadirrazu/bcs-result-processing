<?php

use App\Http\Controllers\NonCadre\Circular\NonCadreCircularController;
use App\Http\Controllers\NonCadre\Choice\NonCadreChoiceController;
use App\Http\Controllers\NonCadre\Allocation\NonCadreAllocationController;
use App\Http\Controllers\NonCadre\NonCadreController;
use App\Http\Controllers\NonCadre\Reporting\NonCadreReportingController;
use App\Http\Controllers\NonCadre\SeatBreakup\NonCadreSeatBreakupController;
use App\Http\Middleware\ConfigureExaminationConnection;
use App\Http\Middleware\EnsureExaminationSelected;
use Illuminate\Support\Facades\Route;

Route::middleware([EnsureExaminationSelected::class, ConfigureExaminationConnection::class])
    ->prefix('non-cadre')->name('non-cadre.')->group(function (): void {
        Route::get('/', [NonCadreController::class, 'index'])->name('index');
        Route::prefix('circular')->name('circular.')->group(function (): void {
            Route::get('/', [NonCadreCircularController::class, 'index'])->name('index');
            Route::get('/template', [NonCadreCircularController::class, 'template'])->name('template');
            Route::post('/import', [NonCadreCircularController::class, 'upload'])->name('import.upload');
            Route::get('/import/{import}/review', [NonCadreCircularController::class, 'review'])->name('import.review');
            Route::post('/import/{import}/approve', [NonCadreCircularController::class, 'approve'])->name('import.approve');
            Route::get('/version/{version}', [NonCadreCircularController::class, 'version'])->name('version');
            Route::get('/version/{version}/pdf', [NonCadreCircularController::class, 'pdf'])->name('version.pdf');
            Route::get('/version/{version}/excel', [NonCadreCircularController::class, 'excel'])->name('version.excel');
            Route::post('/version/{version}/finalize', [NonCadreCircularController::class, 'finalize'])->name('finalize');
        });
        Route::prefix('seat-breakup')->name('seat-breakup.')->group(function (): void {
            Route::get('/', [NonCadreSeatBreakupController::class, 'index'])->name('index');
            Route::get('/template', [NonCadreSeatBreakupController::class, 'template'])->name('template');
            Route::post('/upload', [NonCadreSeatBreakupController::class, 'upload'])->name('upload');
            Route::get('/version/{version}', [NonCadreSeatBreakupController::class, 'version'])->name('version');
            Route::post('/version/{version}/finalize', [NonCadreSeatBreakupController::class, 'finalize'])->name('finalize');
        });
        Route::prefix('choice')->name('choice.')->group(function (): void {
            Route::get('/', [NonCadreChoiceController::class, 'index'])->name('index');
            Route::get('/template', [NonCadreChoiceController::class, 'template'])->name('template');
            Route::post('/upload', [NonCadreChoiceController::class, 'upload'])->name('upload');
            Route::get('/version/{version}', [NonCadreChoiceController::class, 'version'])->name('version');
            Route::get('/version/{version}/progress', [NonCadreChoiceController::class, 'progress'])->name('progress');
            Route::post('/version/{version}/finalize', [NonCadreChoiceController::class, 'finalize'])->name('finalize');
            Route::get('/item/{item}', [NonCadreChoiceController::class, 'showItem'])->name('item.show');
            Route::post('/item/{item}/choice/{code}', [NonCadreChoiceController::class, 'toggle'])->name('item.toggle');
        });
        Route::prefix('allocation')->name('allocation.')->group(function (): void {
            Route::get('/', [NonCadreAllocationController::class, 'index'])->name('index');
            Route::post('/freeze', [NonCadreAllocationController::class, 'freeze'])->name('freeze');
            Route::get('/run/{run}', [NonCadreAllocationController::class, 'run'])->name('run');
            Route::get('/run/{run}/progress', [NonCadreAllocationController::class, 'progress'])->name('progress');
            Route::post('/run/{run}/phase-1', [NonCadreAllocationController::class, 'phase1'])->name('phase1');
            Route::post('/run/{run}/phase-2', [NonCadreAllocationController::class, 'phase2'])->name('phase2');
            Route::post('/run/{run}/review/{review}', [NonCadreAllocationController::class, 'review'])->name('review');
            Route::post('/run/{run}/validate', [NonCadreAllocationController::class, 'validateAllocation'])->name('validate');
            Route::post('/run/{run}/finalize', [NonCadreAllocationController::class, 'finalize'])->name('finalize');
        });
        Route::prefix('reporting')->name('reporting.')->group(function (): void {
            Route::get('/', [NonCadreReportingController::class, 'index'])->name('index');
            Route::get('/{mode}/common-merit', [NonCadreReportingController::class, 'common'])->whereIn('mode', ['verification','booklet'])->name('common');
            Route::post('/{mode}/common-merit/pdf', [NonCadreReportingController::class, 'queuePdf'])->whereIn('mode', ['verification','booklet'])->defaults('scope', 'common')->name('common.pdf');
            Route::get('/verification/post-serial-merit', [NonCadreReportingController::class, 'serialMerit'])->name('serial-merit');
            Route::post('/verification/post-serial-merit/pdf', [NonCadreReportingController::class, 'queuePdf'])->defaults('mode', 'verification')->defaults('scope', 'serial-merit')->name('serial-merit.pdf');
            Route::get('/{mode}/posts', [NonCadreReportingController::class, 'posts'])->whereIn('mode', ['verification','booklet'])->name('posts');
            Route::post('/{mode}/posts/pdf', [NonCadreReportingController::class, 'queuePdf'])->whereIn('mode', ['verification','booklet'])->defaults('scope', 'posts')->name('posts.pdf');
            Route::get('/{mode}/posts/{postCode}/allocated', [NonCadreReportingController::class, 'postAllocated'])->whereIn('mode', ['verification','booklet'])->name('post.allocated');
            Route::post('/{mode}/posts/{postCode}/allocated/pdf', [NonCadreReportingController::class, 'queuePdf'])->whereIn('mode', ['verification','booklet'])->defaults('scope', 'post-allocated')->name('post.allocated.pdf');
            Route::get('/{mode}/posts/{postCode}', [NonCadreReportingController::class, 'post'])->whereIn('mode', ['verification','booklet'])->name('post');
            Route::post('/{mode}/posts/{postCode}/pdf', [NonCadreReportingController::class, 'queuePdf'])->whereIn('mode', ['verification','booklet'])->defaults('scope', 'post')->name('post.pdf');
            Route::post('/exports/txt', [NonCadreReportingController::class, 'txt'])->name('exports.txt');
            Route::get('/docx', [NonCadreReportingController::class, 'docx'])->name('docx');
            Route::get('/docx/sample-template', [NonCadreReportingController::class, 'downloadDocxSample'])->name('docx.sample');
            Route::post('/docx', [NonCadreReportingController::class, 'generateDocx'])->name('docx.generate');
            Route::get('/exports/{exportRun}', [NonCadreReportingController::class, 'exportRun'])->name('exports.show');
            Route::get('/exports/{exportRun}/status', [NonCadreReportingController::class, 'status'])->name('exports.status');
            Route::get('/exports/{exportRun}/download', [NonCadreReportingController::class, 'download'])->name('exports.download');
        });
    });
