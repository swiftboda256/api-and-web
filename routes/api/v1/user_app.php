<?php

use App\Http\Controllers\Api\V1\UserApp\AuthController;
use App\Http\Controllers\Api\V1\UserApp\ProfileController;
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

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'index'])->name('profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('saved-places', [AuthController::class, 'savedPlaces'])->name('saved.places');
    Route::post('saved-places', [AuthController::class, 'savedPlaces'])->name('saved.places');
    Route::patch('saved-places', [AuthController::class, 'savedPlaces'])->name('saved.places');
    Route::delete('saved-places', [AuthController::class, 'savedPlaces'])->name('saved.places');

    Route::get('trips', [AuthController::class, 'trips'])->name('trips');
    Route::post('trips/schedule', [AuthController::class, 'trips'])->name('trips');
    Route::post('order-ride', [AuthController::class, 'orderRide'])->name('order.ride');
    Route::post('order-delivery', [AuthController::class, 'orderRide'])->name('order.ride');
    Route::patch('cancel-ride', [AuthController::class, 'cancelRide'])->name('cancel.ride');
    Route::patch('cancel-delivery', [AuthController::class, 'cancelRide'])->name('cancel.ride');

    Route::get('wallet/balance', [AuthController::class, 'walletBalance'])->name('wallet.balance');
    Route::post('wallet/top-up', [AuthController::class, 'walletBalance'])->name('wallet.balance');
    Route::post('wallet/withdraw', [AuthController::class, 'walletBalance'])->name('wallet.balance');
    Route::get('wallet/history', [AuthController::class, 'walletBalance'])->name('wallet.balance');

});
