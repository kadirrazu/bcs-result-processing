<?php
use App\Http\Controllers\TabulationController;use App\Http\Middleware\ConfigureExaminationConnection;use App\Http\Middleware\EnsureExaminationSelected;use Illuminate\Support\Facades\Route;
Route::middleware([EnsureExaminationSelected::class,ConfigureExaminationConnection::class])->prefix('tabulation')->name('tabulation.')->group(function(){
 Route::get('/',[TabulationController::class,'index'])->name('index');Route::post('/generate',[TabulationController::class,'start'])->name('generate');
 Route::get('/runs/{run}',[TabulationController::class,'runShow'])->name('run.show');Route::get('/runs/{run}/status',[TabulationController::class,'runStatus'])->name('run.status');
 Route::get('/results',[TabulationController::class,'results'])->name('results');Route::get('/results/export/xlsx',[TabulationController::class,'export'])->name('export.xlsx');
 Route::post('/finalizations/{finalization}/rollback',[TabulationController::class,'rollback'])->name('rollback');Route::get('/results/{result}',[TabulationController::class,'show'])->name('show');Route::get('/results/{result}/pdf',[TabulationController::class,'pdf'])->name('pdf');Route::post('/finalize',[TabulationController::class,'finalize'])->name('finalize');
});
