<?php

namespace App\Http\Controllers\Api\V1\UserApp;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UserApp\Trip\CancelTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\EstimateTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\IndexTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\RateTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\ScheduleTripRequest;
use App\Http\Requests\Api\V1\UserApp\Trip\ShowTripRequest;
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

class TripController extends Controller
{
    public function index(IndexTripRequest $request, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success(new TripCollection($tripService->list($user, $request->validated())));
    }

    public function show(ShowTripRequest $request, int $trip, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return self::success($this->bookingResource($tripService->show($user, $trip, $request->validated('type'))));
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
     * Cancels the customer's own ride booking -- ride, ride-share or posted ride.
     * {tripPassenger} is the booking id, the same id GET trips/{id} takes.
     */
    public function cancelRide(CancelTripRequest $request, int $tripPassenger, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $booking = $tripService->cancelPassenger($user, $tripPassenger, $request->validated('cancellation_reason_id'));

        return self::success(new TripPassengerResource($booking));
    }

    /**
     * Cancels the customer's own delivery booking -- delivery or pooled delivery.
     * {delivery} is the delivery booking id (delivery_details_id).
     */
    public function cancelDelivery(CancelTripRequest $request, int $delivery, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $booking = $tripService->cancelDelivery($user, $delivery, $request->validated('cancellation_reason_id'));

        return self::success(new DeliveryResource($booking));
    }

    /**
     * Kept so the old route answers with a clear error -- rating always goes through
     * ratePassengerDriver()/rateDeliveryDriver() now.
     */
    public function rateRider(RateTripRequest $request, int $trip, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $tripService->rateRider($user, $trip, $request->validated());
    }

    public function ratePassengerDriver(RateTripRequest $request, int $tripPassenger, TripService $tripService): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $rating = $tripService->ratePassengerDriver($user, $tripPassenger, $request->validated());

        return self::success(new RatingResource($rating), status: 201);
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
