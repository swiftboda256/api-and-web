<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RiderApp\ZoneResource;
use App\Services\Rider\ZoneService;
use Illuminate\Http\JsonResponse;

class ZoneController extends Controller
{
    public function index(ZoneService $zoneService): JsonResponse
    {
        return self::success(ZoneResource::collection($zoneService->list()));
    }
}
