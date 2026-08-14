<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\UserApp\TripCancellationReasonResource;
use App\Services\Trip\TripCancellationReasonService;
use Illuminate\Http\JsonResponse;

class TripCancellationReasonController extends Controller
{
    public function index(TripCancellationReasonService $tripCancellationReasonService): JsonResponse
    {
        return self::success(TripCancellationReasonResource::collection($tripCancellationReasonService->list('customer')));
    }
}
