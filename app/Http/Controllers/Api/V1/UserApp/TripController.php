<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Trip\CancelTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\EstimateTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\IndexTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\RateTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\ScheduleTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\StoreTripRequest;
use App\Http\Resources\Api\V1\UserApp\DeliveryResource;
use App\Http\Resources\Api\V1\UserApp\RatingResource;
use App\Http\Resources\Api\V1\UserApp\TripCollection;
use App\Http\Resources\Api\V1\UserApp\TripEstimateResource;
use App\Http\Resources\Api\V1\UserApp\TripPassengerResource;
use App\Models\DeliveryDetails;
use App\Models\TripPassenger;
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

        return self::success($this->bookingResource($tripService->show($user, $trip)));
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

        $booking = $tripService->book($user, $request->validated(), CarbonImmutable::now());

        return self::success($this->bookingResource($booking), status: 201);
    }

    public function schedule(ScheduleTripRequest $request, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validated();
        $requestedAt = CarbonImmutable::parse($data['requested_at']);
        unset($data['requested_at']);

        $booking = $tripService->book($user, $data, $requestedAt);

        return self::success($this->bookingResource($booking), status: 201);
    }

    /**
     * Kept only so a client still on the old trip-id-based cancel endpoint gets a clear
     * validation error (via TripService::cancel(), which always throws now) instead of a
     * crash -- every trip type cancels through cancelPassenger()/cancelDelivery() instead.
     */
    public function cancel(CancelTripRequest $request, int $trip, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tripService->cancel($user, $trip, $request->validated('cancellation_reason_id'));
    }

    /**
     * Kept for the same reason as cancel() -- rating always goes through
     * ratePassengerDriver()/rateDeliveryDriver() now.
     */
    public function rateRider(RateTripRequest $request, int $trip, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tripService->rateRider($user, $trip, $request->validated());
    }

    public function cancelPassenger(CancelTripRequest $request, int $tripPassenger, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $passenger = $tripService->cancelPassenger($user, $tripPassenger, $request->validated('cancellation_reason_id'));

        return self::success(new TripPassengerResource($passenger));
    }

    public function ratePassengerDriver(RateTripRequest $request, int $tripPassenger, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rating = $tripService->ratePassengerDriver($user, $tripPassenger, $request->validated());

        return self::success(new RatingResource($rating), status: 201);
    }

    public function cancelDelivery(CancelTripRequest $request, int $delivery, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $delivery = $tripService->cancelDelivery($user, $delivery, $request->validated('cancellation_reason_id'));

        return self::success(new DeliveryResource($delivery));
    }

    public function rateDeliveryDriver(RateTripRequest $request, int $delivery, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rating = $tripService->rateDeliveryDriver($user, $delivery, $request->validated());

        return self::success(new RatingResource($rating), status: 201);
    }

    private function bookingResource(TripPassenger|DeliveryDetails $booking): TripPassengerResource|DeliveryResource
    {
        return $booking instanceof DeliveryDetails
            ? new DeliveryResource($booking)
            : new TripPassengerResource($booking);
    }
}
