<?php

use App\Http\Controllers\BachelorSubjectController;
use App\Http\Controllers\CadreMasterController;
use App\Http\Controllers\CadreSubMasterController;
use App\Http\Controllers\MasterDataExportController;
use App\Http\Controllers\MasterDataImportController;
use App\Http\Controllers\PostRelatedSubjectController;
use Illuminate\Support\Facades\Route;

Route::prefix('master-data/exports')->name('master-data.exports.')->group(function () {
    Route::get('{type}/excel', [MasterDataExportController::class, 'excel'])->name('excel');
    Route::get('{type}/pdf', [MasterDataExportController::class, 'pdf'])->name('pdf');
});

Route::prefix('master-data/imports')->name('master-data.imports.')->group(function () {
    Route::get('{type}', [MasterDataImportController::class, 'create'])->name('create');
    Route::get('{type}/template', [MasterDataImportController::class, 'template'])->name('template');
    Route::post('{type}/preview', [MasterDataImportController::class, 'preview'])->name('preview');
    Route::post('{type}', [MasterDataImportController::class, 'store'])->name('store');
});

Route::resource('cadre-masters', CadreMasterController::class)->except('destroy');
Route::resource('cadre-sub-masters', CadreSubMasterController::class)->except('destroy');
Route::resource('bachelor-subjects', BachelorSubjectController::class)->except('destroy');
Route::resource('post-related-subjects', PostRelatedSubjectController::class)->except('destroy');
