<?php

use App\Http\Controllers\Api\V1\RiderApp\AuthController;
use App\Http\Controllers\Api\V1\RiderApp\DeviceController;
use App\Http\Controllers\Api\V1\RiderApp\NotificationController;
use App\Http\Controllers\Api\V1\RiderApp\ProfileController;
use App\Http\Controllers\Api\V1\RiderApp\RideController;
use App\Http\Controllers\Api\V1\RiderApp\VehicleTypeController;
use App\Http\Controllers\Api\V1\RiderApp\WalletController;

Route::prefix('auth')->name('auth.')->group(function () {
    Route::post('login', [AuthController::class, 'login'])->name('otp.request');
    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])->name('otp.verify');
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum')->name('logout');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('profile', [ProfileController::class, 'index'])->name('profile');
    Route::patch('profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('vehicle-types', [VehicleTypeController::class, 'index'])->name('vehicle-types');

    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::post('/', [WalletController::class, 'store'])->name('store');
        Route::get('balance', [WalletController::class, 'balance'])->name('balance');
        Route::patch('pin-change', [WalletController::class, 'updatePin'])->name('pin.update');
        Route::post('top-up', [WalletController::class, 'topUp'])->name('top-up');
        Route::post('withdraw', [WalletController::class, 'withdraw'])->name('withdraw');
        Route::get('withdrawal-requests', [WalletController::class, 'withdrawalRequests'])->name('withdrawal-requests');
        Route::get('history', [WalletController::class, 'history'])->name('history');
    });

    Route::prefix('rides')->name('rides.')->group(function () {
        Route::get('/', [RideController::class, 'index'])->name('index');
        Route::get('/new', [RideController::class, 'newTrips'])->name('new');
        Route::patch('{trip}/accept', [RideController::class, 'accept'])->name('accept');
        Route::patch('{trip}/start', [RideController::class, 'start'])->name('start');
        Route::patch('{trip}/cancel', [RideController::class, 'cancel'])->name('cancel');
        Route::patch('{trip}/end', [RideController::class, 'end'])->name('end');
    });

    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/read', [NotificationController::class, 'markAsRead'])->name('read');
        Route::post('/delete', [NotificationController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('devices')->name('devices.')->group(function () {
        Route::get('/', [DeviceController::class, 'index'])->name('index');
        Route::patch('{id}', [DeviceController::class, 'update'])->name('update');
        Route::delete('{id}', [DeviceController::class, 'destroy'])->name('destroy');
    });

});
