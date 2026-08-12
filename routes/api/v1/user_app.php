<?php

use App\Http\Controllers\Api\V1\UserApp\AddressController;
use App\Http\Controllers\Api\V1\UserApp\AuthController;
use App\Http\Controllers\Api\V1\UserApp\DeviceController;
use App\Http\Controllers\Api\V1\UserApp\EmergencyContactController;
use App\Http\Controllers\Api\V1\UserApp\NotificationController;
use App\Http\Controllers\Api\V1\UserApp\ProfileController;
use App\Http\Controllers\Api\V1\UserApp\RiderController;
use App\Http\Controllers\Api\V1\UserApp\SupportCategoryController;
use App\Http\Controllers\Api\V1\UserApp\SupportTicketController;
use App\Http\Controllers\Api\V1\UserApp\TripController;
use App\Http\Controllers\Api\V1\UserApp\WalletController;
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
        Route::get('nearby-riders', [RiderController::class, 'nearbyRiders'])->name('riders');
        Route::get('/', [TripController::class, 'index'])->name('index');
        Route::get('/{trip}', [TripController::class, 'show'])->name('show');
        Route::post('estimate', [TripController::class, 'estimateTrip'])->name('estimate');
        Route::post('schedule', [TripController::class, 'schedule'])->name('schedule');
        Route::post('order-ride', [TripController::class, 'store'])->name('order-ride');
        Route::post('order-delivery', [TripController::class, 'store'])->name('order-delivery');
        Route::patch('cancel-ride/{trip}', [TripController::class, 'cancel'])->name('cancel-ride');
        Route::patch('cancel-delivery/{trip}', [TripController::class, 'cancel'])->name('cancel-delivery');
    });

    Route::prefix('wallet')->name('wallet.')->group(function () {
        Route::post('/', [WalletController::class, 'store'])->name('store');
        Route::get('balance', [WalletController::class, 'balance'])->name('balance');
        Route::patch('pin-change', [WalletController::class, 'updatePin'])->name('pin.update');
        Route::post('top-up', [WalletController::class, 'topUp'])->name('top-up');
        Route::post('withdraw', [WalletController::class, 'withdraw'])->name('withdraw');
        Route::get('withdrawal-requests', [WalletController::class, 'withdrawalRequests'])->name('withdrawal-requests');
        Route::get('history', [WalletController::class, 'history'])->name('history');
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
