<?php

use App\Http\Controllers\SetPasswordController;
use App\Http\Controllers\WebController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WebController::class, 'index'])->name('home');
Route::view('/privacy-policy', 'legal.privacy-policy')->name('privacy-policy');

Route::get('/set-password/{user}', [SetPasswordController::class, 'show'])->name('set-password.show')->middleware('signed');
Route::post('/set-password/{user}', [SetPasswordController::class, 'store'])->name('set-password.store')->middleware('signed');
