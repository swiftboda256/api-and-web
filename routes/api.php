<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return response()->json([
        'status' => 200,
        'message' => 'SwiftBoda API V1',
    ]);
});
