<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Trip\CancelTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\EstimateTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\IndexTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\RateTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\ScheduleTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\StoreTripRequest;
use App\Http\Resources\Api\V1\UserApp\RatingResource;
use App\Http\Resources\Api\V1\UserApp\TripCollection;
use App\Http\Resources\Api\V1\UserApp\TripEstimateResource;
use App\Http\Resources\Api\V1\UserApp\TripResource;
use App\Models\User;
use App\Services\Trip\TripService;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripController extends Controller
{
    public function index(IndexTripRequest $request, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new TripCollection($tripService->list($user, $request->validated())));
    }

    public function show(Request $request, int $trip, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new TripResource($tripService->show($user, $trip)));
    }

    public function estimateTrip(EstimateTripRequest $request, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new TripEstimateResource($tripService->estimate($user, $request->validated())));
    }

    public function store(StoreTripRequest $request, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $trip = $tripService->book($user, $request->validated(), CarbonImmutable::now());

        return self::success(new TripResource($trip), status: 201);
    }

    public function schedule(ScheduleTripRequest $request, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();
        $requestedAt = CarbonImmutable::parse($data['requested_at']);
        unset($data['requested_at']);

        $trip = $tripService->book($user, $data, $requestedAt);

        return self::success(new TripResource($trip), status: 201);
    }

    public function cancel(CancelTripRequest $request, int $trip, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $cancelledTrip = $tripService->cancel($user, $trip, $request->validated('cancellation_reason_id'));

        return self::success(new TripResource($cancelledTrip));
    }

    public function rateRider(RateTripRequest $request, int $trip, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rating = $tripService->rateRider($user, $trip, $request->validated());

        return self::success(new RatingResource($rating), status: 201);
    }
}
