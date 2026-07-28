<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\RiderApp\VehicleTypeResource;
use App\Models\VehicleType;
use Illuminate\Http\JsonResponse;

class VehicleTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $vehicleTypes = VehicleType::query()->where('is_active', true)->orderBy('name')->get();

        return self::success(VehicleTypeResource::collection($vehicleTypes));
    }
}
