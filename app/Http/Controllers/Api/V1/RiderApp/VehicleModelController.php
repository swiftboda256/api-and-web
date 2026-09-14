<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RiderApp\VehicleModel\IndexVehicleModelRequest;
use App\Http\Resources\Api\V1\RiderApp\VehicleModelResource;
use App\Services\Rider\VehicleModelService;
use Illuminate\Http\JsonResponse;

class VehicleModelController extends Controller
{
    public function index(IndexVehicleModelRequest $request, VehicleModelService $vehicleModelService): JsonResponse
    {
        return self::success(VehicleModelResource::collection($vehicleModelService->list($request->validated())));
    }
}
