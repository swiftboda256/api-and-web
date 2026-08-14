<?php

use App\Http\Controllers\Api\V1\RiderApp\AuthController;
use App\Http\Controllers\Api\V1\RiderApp\DeviceController;
use App\Http\Controllers\Api\V1\RiderApp\EmergencyContactController;
use App\Http\Controllers\Api\V1\RiderApp\NotificationController;
use App\Http\Controllers\Api\V1\RiderApp\ProfileController;
use App\Http\Controllers\Api\V1\RiderApp\RideController;
use App\Http\Controllers\Api\V1\RiderApp\SupportCategoryController;
use App\Http\Controllers\Api\V1\RiderApp\SupportTicketController;
use App\Http\Controllers\Api\V1\RiderApp\TripCancellationReasonController;
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
        Route::get('cancellation-reasons', [TripCancellationReasonController::class, 'index'])->name('cancellation-reasons');
        Route::get('/', [RideController::class, 'index'])->name('index');
        Route::get('/new', [RideController::class, 'newTrips'])->name('new');
        Route::get('/{trip}', [RideController::class, 'show'])->name('show')->whereNumber('trip');
        Route::patch('{trip}/accept', [RideController::class, 'accept'])->name('accept');
        Route::patch('{trip}/arrive', [RideController::class, 'arrive'])->name('arrive')->whereNumber('trip');
        Route::patch('{trip}/start', [RideController::class, 'start'])->name('start');
        Route::patch('{trip}/cancel', [RideController::class, 'cancel'])->name('cancel');
        Route::patch('{trip}/end', [RideController::class, 'end'])->name('end');
        Route::post('{trip}/location', [RideController::class, 'logLocation'])->name('log-location');
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

    Route::get('support-categories', [SupportCategoryController::class, 'index'])->name('support-categories');

    Route::prefix('support-tickets')->name('support-tickets.')->group(function () {
        Route::get('/', [SupportTicketController::class, 'index'])->name('index');
        Route::get('/{id}', [SupportTicketController::class, 'show'])->name('show');
        Route::post('/', [SupportTicketController::class, 'store'])->name('store');
    });

    Route::prefix('emergency-contacts')->name('emergency-contacts.')->group(function () {
        Route::get('/', [EmergencyContactController::class, 'index'])->name('index');
        Route::post('/', [EmergencyContactController::class, 'store'])->name('store');
        Route::patch('{id}', [EmergencyContactController::class, 'update'])->name('update');
        Route::delete('{id}', [EmergencyContactController::class, 'destroy'])->name('destroy');
    });

});
