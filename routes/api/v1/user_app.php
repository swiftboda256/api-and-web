<?php

use App\Http\Controllers\Api\V1\UserApp\AuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return response()->json([
        'status' => 200,
        'message' => 'SwiftBoda API v1',
    ]);
});

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('otp.request');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('otp.verify');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
});
