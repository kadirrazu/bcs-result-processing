<?php
use App\Http\Controllers\RegistrationMasterController;
use Illuminate\Support\Facades\Route;
Route::prefix('master-data/registration')->name('registration-masters.')->group(function(){Route::get('{type}',[RegistrationMasterController::class,'index'])->name('index');Route::get('{type}/create',[RegistrationMasterController::class,'create'])->name('create');Route::post('{type}',[RegistrationMasterController::class,'store'])->name('store');Route::get('{type}/{id}/edit',[RegistrationMasterController::class,'edit'])->name('edit');Route::put('{type}/{id}',[RegistrationMasterController::class,'update'])->name('update');});
