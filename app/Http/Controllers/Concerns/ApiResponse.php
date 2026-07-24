<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\JsonResponse;

/**
 * The single place the API's success/failure JSON envelope is defined.
 * Composed onto the base Controller, so every API controller gets
 * $this->success()/$this->error() for free, and the global exception
 * handler (bootstrap/app.php) reuses the same shape via a static call
 * on the Controller class.
 */
trait ApiResponse
{
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'data' => $data,
        ], $status);
    }

    public static function error(string $message = 'Something went wrong', int $status = 400, mixed $errors = null): JsonResponse
    {
        return response()->json([
            'status' => $status,
            'message' => $message,
            'errors' => $errors,
        ], $status);
    }
}
