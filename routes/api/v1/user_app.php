<?php

use App\Http\Controllers\Api\V1\UserApp\AddressController;
use App\Http\Controllers\Api\V1\UserApp\AuthController;
use App\Http\Controllers\Api\V1\UserApp\ProfileController;
use App\Http\Controllers\Api\V1\UserApp\TripController;
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

    Route::prefix('saved-places')->name('saved-places.')->group(function () {
        Route::get('/', [AddressController::class, 'index'])->name('index');
        Route::post('/', [AddressController::class, 'store'])->name('store');
        Route::patch('{id}', [AddressController::class, 'update'])->name('update');
        Route::delete('{id}', [AddressController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('trips')->name('trips.')->group(function () {
        Route::get('/', [TripController::class, 'index'])->name('index');
        Route::post('estimate', [TripController::class, 'estimateTrip'])->name('estimate');
        Route::post('schedule', [TripController::class, 'schedule'])->name('schedule');
        Route::post('order-ride', [TripController::class, 'store'])->name('order-ride');
        Route::post('order-delivery', [TripController::class, 'store'])->name('order-delivery');
        Route::patch('cancel-ride/{trip}', [TripController::class, 'cancel'])->name('cancel-ride');
        Route::patch('cancel-delivery/{trip}', [TripController::class, 'cancel'])->name('cancel-delivery');
    });

//    Route::get('wallet/balance', [AuthController::class, 'walletBalance'])->name('wallet.balance');
//    Route::post('wallet/top-up', [AuthController::class, 'walletBalance'])->name('wallet.balance');
//    Route::post('wallet/withdraw', [AuthController::class, 'walletBalance'])->name('wallet.balance');
//    Route::get('wallet/history', [AuthController::class, 'walletBalance'])->name('wallet.balance');

});
