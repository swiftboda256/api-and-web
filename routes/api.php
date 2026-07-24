<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return response()->json([
        'status' => 200,
        'message' => 'SwiftBoda',
    ]);
});

Route::prefix('v1/user-app')->name('api.v1.user-app.')->group(base_path('routes/api/v1/user_app.php'));
