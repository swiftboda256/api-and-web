<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\PostedRide\BookPostedRideRequest;
use App\Http\Requests\Api\V1\UserApp\PostedRide\IndexPostedRideRequest;
use App\Http\Resources\Api\V1\UserApp\PostedRideCollection;
use App\Http\Resources\Api\V1\UserApp\PostedRideResource;
use App\Http\Resources\Api\V1\UserApp\TripPassengerResource;
use App\Models\User;
use App\Services\Trip\PostedRideService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Rides drivers have posted for customers to book seats on. A booking is cancelled through
 * the usual trips cancel endpoint, like any other ride.
 */
class PostedRideController extends Controller
{
    public function index(IndexPostedRideRequest $request, PostedRideService $postedRideService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new PostedRideCollection($postedRideService->browse($user, $request->validated())));
    }

    public function show(Request $request, int $trip, PostedRideService $postedRideService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new PostedRideResource($postedRideService->showOpen($user, $trip)));
    }

    public function book(BookPostedRideRequest $request, int $trip, PostedRideService $postedRideService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $passenger = $postedRideService->book($user, $trip, $request->validated());

        return self::success(new TripPassengerResource($passenger), 'Booking requested. Waiting for the driver to approve.', 201);
    }
}
