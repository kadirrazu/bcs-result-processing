<?php
use App\Http\Controllers\HistoricalPunishmentController;
use Illuminate\Support\Facades\Route;
Route::prefix('historical-punishments')->name('historical-punishments.')->group(function(){
 Route::get('/',[HistoricalPunishmentController::class,'index'])->name('index');
 Route::get('/create',[HistoricalPunishmentController::class,'create'])->name('create');
 Route::post('/',[HistoricalPunishmentController::class,'store'])->name('store');
 Route::get('/{historicalPunishment}/edit',[HistoricalPunishmentController::class,'edit'])->name('edit');
 Route::put('/{historicalPunishment}',[HistoricalPunishmentController::class,'update'])->name('update');
 Route::delete('/{historicalPunishment}',[HistoricalPunishmentController::class,'destroy'])->name('destroy');
 Route::get('/batch-import',[HistoricalPunishmentController::class,'importPage'])->name('import.page');
 Route::post('/batch-import',[HistoricalPunishmentController::class,'import'])->name('import');
 Route::get('/template/xlsx',[HistoricalPunishmentController::class,'template'])->name('template');
});
