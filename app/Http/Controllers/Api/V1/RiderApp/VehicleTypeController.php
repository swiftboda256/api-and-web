<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RiderApp\VehicleTypeResource;
use App\Services\Rider\VehicleTypeService;
use Illuminate\Http\JsonResponse;

class VehicleTypeController extends Controller
{
    public function index(VehicleTypeService $vehicleTypeService): JsonResponse
    {
        return self::success(VehicleTypeResource::collection($vehicleTypeService->list()));
    }
}
