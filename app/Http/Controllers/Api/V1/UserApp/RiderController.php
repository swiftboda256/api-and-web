<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Rider\NearbyRidersRequest;
use App\Http\Resources\Api\V1\UserApp\NearbyRiderResource;
use App\Services\Rider\RiderSearchService;
use Illuminate\Http\JsonResponse;

class RiderController extends Controller
{
    public function nearbyRiders(NearbyRidersRequest $request, RiderSearchService $riderSearchService): JsonResponse
    {
        return self::success(NearbyRiderResource::collection($riderSearchService->nearby($request->validated())));
    }
}
