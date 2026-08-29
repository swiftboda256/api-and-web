<?php

use App\Http\Controllers\Api\V1\Payments\YoPaymentController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return response()->json([
        'status' => 200,
        'message' => 'SwiftBoda',
    ]);
});

Route::prefix('v1/user-app')->name('api.v1.user-app.')->group(base_path('routes/api/v1/user_app.php'));
Route::prefix('v1/rider-app')->name('api.v1.rider-app.')->group(base_path('routes/api/v1/rider_app.php'));

Route::post('v1/payments/yo/ipn', [YoPaymentController::class, 'handleIPN'])->name('api.v1.payments.yo.ipn');
Route::post('v1/payments/yo/failure', [YoPaymentController::class, 'handleFailure'])->name('api.v1.payments.yo.failure');
