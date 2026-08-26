<?php

use App\Http\Controllers\SetPasswordController;
use App\Http\Controllers\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WaitlistController::class, 'index'])->name('home');
Route::post('/waitlist', [WaitlistController::class, 'store'])->name('waitlist.store');

Route::get('/set-password/{user}', [SetPasswordController::class, 'show'])->name('set-password.show')->middleware('signed');
Route::post('/set-password/{user}', [SetPasswordController::class, 'store'])->name('set-password.store')->middleware('signed');
