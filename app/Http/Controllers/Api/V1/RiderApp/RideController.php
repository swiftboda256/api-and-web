<?php

namespace App\Http\Controllers\Api\V1\RiderApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\RiderApp\Ride\CancelRideRequest;
use App\Http\Requests\Api\V1\RiderApp\Ride\IndexRideRequest;
use App\Http\Requests\Api\V1\RiderApp\Ride\NearbyRidesRequest;
use App\Http\Resources\Api\V1\RiderApp\RideCollection;
use App\Http\Resources\Api\V1\RiderApp\RideResource;
use App\Models\User;
use App\Services\Trip\RiderTripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RideController extends Controller
{
    public function index(IndexRideRequest $request, RiderTripService $riderTripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new RideCollection($riderTripService->list($user, $request->validated())));
    }

    public function newTrips(NearbyRidesRequest $request, RiderTripService $riderTripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new RideCollection($riderTripService->nearby($user, $request->validated())));
    }

    public function accept(Request $request, int $trip, RiderTripService $riderTripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new RideResource($riderTripService->accept($user, $trip)));
    }

    public function start(Request $request, int $trip, RiderTripService $riderTripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new RideResource($riderTripService->start($user, $trip)));
    }

    public function cancel(CancelRideRequest $request, int $trip, RiderTripService $riderTripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new RideResource($riderTripService->cancel($user, $trip, $request->validated('cancellation_reason_id'))));
    }

    public function end(Request $request, int $trip, RiderTripService $riderTripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new RideResource($riderTripService->end($user, $trip)));
    }
}
