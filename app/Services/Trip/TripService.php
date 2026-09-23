<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\DeliveryDetails;
use App\Models\DeliveryStop;
use App\Models\PricingRule;
use App\Models\PromoCodeRedemption;
use App\Models\Rating;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Checkout\CheckoutService;
use App\Services\Push\FcmGateway;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class TripService
{
    public function __construct(
        private FcmGateway $pushGateway,
        private CheckoutService $checkout,
        private RideShareMatchingService $rideShareMatching,
        private DeliveryPoolingMatchingService $deliveryPoolingMatching,
    ) {}

    /**
     * ride/ride_share live on trip_passengers, delivery/delivery_share on delivery_details.
     * A type filter routes to the matching source; no filter merges both (sorted by
     * requested_at only -- sort_by_fare isn't supported for the merged view since the two
     * sources don't share a fare column shape).
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TripPassenger|DeliveryDetails>
     */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $type = $filters['type'] ?? null;

        if (in_array($type, ['delivery', 'delivery_share'], true)) {
            return $this->listDeliveries($user, $filters);
        }

        if (in_array($type, ['ride', 'ride_share'], true)) {
            return $this->listPassengerTrips($user, $filters);
        }

        return $this->listAllTrips($user, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TripPassenger|DeliveryDetails>
     */
    private function listPassengerTrips(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->passengerTripsQuery($user, $filters)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->whereHas('trip', fn ($query) => $query->where('type', $type)))
            ->with($this->passengerEagerLoads());

        if (($filters['sort_by_fare'] ?? null) !== null) {
            $query->orderBy('estimated_fare', $filters['sort_by_fare']);
        } else {
            $query->orderByDesc('requested_at');
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        return new LengthAwarePaginator(
            $this->asPassengerOrDeliveryItems($paginator->items()),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TripPassenger|DeliveryDetails>
     */
    private function listDeliveries(User $user, array $filters): LengthAwarePaginator
    {
        $query = $this->deliveryTripsQuery($user, $filters)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->whereHas('trip', fn ($query) => $query->where('type', $type)))
            ->with($this->deliveryEagerLoads());

        if (($filters['sort_by_fare'] ?? null) !== null) {
            $query->orderBy('estimated_fare', $filters['sort_by_fare']);
        } else {
            $query->orderByDesc('requested_at');
        }

        $paginator = $query->paginate((int) ($filters['per_page'] ?? 15));

        return new LengthAwarePaginator(
            $this->asPassengerOrDeliveryItems($paginator->items()),
            $paginator->total(),
            $paginator->perPage(),
            $paginator->currentPage(),
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * Passes a page of items through untouched -- exists purely so the caller can declare
     * LengthAwarePaginator<int, TripPassenger|DeliveryDetails> from a page of only one of
     * the two types. LengthAwarePaginator's generic isn't covariant, so constructing a new
     * instance directly from an already single-type items array keeps that narrower type;
     * routing it through an untyped parameter here is what actually widens it.
     *
     * @param  array<int, TripPassenger|DeliveryDetails>  $items
     * @return list<TripPassenger|DeliveryDetails>
     */
    private function asPassengerOrDeliveryItems(array $items): array
    {
        return array_values($items);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, TripPassenger|DeliveryDetails>
     */
    private function listAllTrips(User $user, array $filters): LengthAwarePaginator
    {
        $perPage = (int) ($filters['per_page'] ?? 15);
        $page = Paginator::resolveCurrentPage();

        $passengers = $this->passengerTripsQuery($user, $filters)->with($this->passengerEagerLoads())->get();
        $deliveries = $this->deliveryTripsQuery($user, $filters)->with($this->deliveryEagerLoads())->get();

        $merged = $passengers->concat($deliveries)->sortByDesc('requested_at')->values();

        return new LengthAwarePaginator(
            $merged->forPage($page, $perPage)->values(),
            $merged->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath()],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<TripPassenger>
     */
    private function passengerTripsQuery(User $user, array $filters): Builder
    {
        return TripPassenger::query()
            ->where('customer_id', $user->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('requested_at', '>=', CarbonImmutable::parse($from)))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('requested_at', '<=', CarbonImmutable::parse($to)));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<DeliveryDetails>
     */
    private function deliveryTripsQuery(User $user, array $filters): Builder
    {
        return DeliveryDetails::query()
            ->where('sender_id', $user->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('requested_at', '>=', CarbonImmutable::parse($from)))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('requested_at', '<=', CarbonImmutable::parse($to)));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function estimate(User $user, array $data): array
    {
        $pickup = Point::makeGeodetic((float) $data['pickup_latitude'], (float) $data['pickup_longitude']);

        $zone = $this->checkout->resolveZone($pickup);
        $pricingRule = $this->checkout->resolvePricingRule($zone->id, (int) $data['vehicle_type_id']);

        $distanceKm = (float) $data['distance_km'];
        $durationMinutes = $this->checkout->durationMinutes($distanceKm);

        // Shared (ride_share/delivery_share) estimate: what this segment would cost
        // discounted, as if matched with zero detour. The real fare is only known once
        // booking actually runs matching -- an inserted detour changes nothing about the
        // segment's own distance/time, but a request may still land on a brand-new
        // (unmatched) trip instead, which is priced the same way regardless.
        if (in_array($data['type'], ['ride_share', 'delivery_share'], true)) {
            $fare = $data['type'] === 'ride_share'
                ? $this->checkout->calculateRideShareFare($pricingRule, $zone, (int) $data['vehicle_type_id'], $distanceKm, $durationMinutes, now())
                : $this->checkout->calculateDeliveryShareFare($pricingRule, $zone, (int) $data['vehicle_type_id'], $distanceKm, $durationMinutes, now());

            return [
                'zone' => ['id' => $zone->id, 'name' => $zone->name],
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'currency_code' => $zone->currency_code,
                'base_fare' => $fare['base_fare'],
                'distance_fare' => $fare['distance_fare'],
                'time_fare' => $fare['time_fare'],
                'surge_multiplier' => $fare['surge_multiplier'],
                'surge_amount' => $fare['surge_amount'],
                'discount_amount' => 0.0,
                'promo_code' => null,
                'discount_percentage' => $fare['discount_percentage'],
                'estimated_fare' => $this->checkout->roundFare($fare['fare']),
            ];
        }

        $fare = $this->checkout->calculateFare($pricingRule, $zone, (int) $data['vehicle_type_id'], $distanceKm, $durationMinutes, now());
        $promoResult = $this->checkout->resolvePromoDiscount($data['promo_code'] ?? null, $user, $zone, (int) $data['vehicle_type_id'], $fare['fare']);

        return [
            'zone' => ['id' => $zone->id, 'name' => $zone->name],
            'distance_km' => $distanceKm,
            'duration_minutes' => $durationMinutes,
            'currency_code' => $zone->currency_code,
            'base_fare' => $fare['base_fare'],
            'distance_fare' => $fare['distance_fare'],
            'time_fare' => $fare['time_fare'],
            'surge_multiplier' => $fare['surge_multiplier'],
            'surge_amount' => $fare['surge_amount'],
            'discount_amount' => $promoResult['discount'],
            'promo_code' => $promoResult['promo']?->code,
            'discount_percentage' => null,
            'estimated_fare' => $this->checkout->roundFare(round($fare['fare'] - $promoResult['discount'], 2)),
        ];
    }

    /**
     * Tries a TripPassenger first (ride/ride_share), then a DeliveryDetails
     * (delivery/delivery_share).
     */
    public function show(User $user, int $tripId): TripPassenger|DeliveryDetails
    {
        $passenger = TripPassenger::query()
            ->where('customer_id', $user->id)
            ->where('id', $tripId)
            ->with($this->passengerEagerLoads())
            ->first();

        if ($passenger !== null) {
            return $passenger;
        }

        return DeliveryDetails::query()
            ->where('sender_id', $user->id)
            ->where('id', $tripId)
            ->with($this->deliveryEagerLoads())
            ->firstOrFail();
    }

    /**
     * Everything TripPassengerResource needs -- its own customer/stops/cancellationReason,
     * plus the trip's rider and vehicle type.
     *
     * @return array<int, string>
     */
    private function passengerEagerLoads(): array
    {
        return ['customer', 'fareBreakdown', 'stops', 'cancellationReason', 'trip.rider.riderProfile', 'trip.vehicleType'];
    }

    /**
     * Everything DeliveryResource needs -- its own sender/stops/cancellationReason, plus the
     * trip's rider and vehicle type.
     *
     * @return array<int, string>
     */
    private function deliveryEagerLoads(): array
    {
        return ['sender', 'stops', 'cancellationReason', 'trip.rider.riderProfile', 'trip.vehicleType'];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function book(User $user, array $data, CarbonImmutable $requestedAt): TripPassenger|DeliveryDetails
    {
        if ($user->status === 'requested_delete') {
            throw ValidationException::withMessages([
                'account' => 'You cannot request trips while your account deletion request is pending. Cancel the deletion request to continue.',
            ]);
        }

        $pickup = Point::makeGeodetic((float) $data['pickup_latitude'], (float) $data['pickup_longitude']);
        $dropoff = Point::makeGeodetic((float) $data['dropoff_latitude'], (float) $data['dropoff_longitude']);

        $zone = $this->checkout->resolveZone($pickup);
        $pricingRule = $this->checkout->resolvePricingRule($zone->id, (int) $data['vehicle_type_id']);

        return match ($data['type']) {
            'ride_share' => $this->bookRideShare($user, $data, $pickup, $dropoff, $zone, $pricingRule, $requestedAt),
            'ride' => $this->bookRide($user, $data, $pickup, $dropoff, $zone, $pricingRule, $requestedAt),
            'delivery_share' => $this->bookDeliveryShare($user, $data, $pickup, $dropoff, $zone, $pricingRule, $requestedAt),
            default => $this->bookDelivery($user, $data, $pickup, $dropoff, $zone, $pricingRule, $requestedAt),
        };
    }

    /**
     * A solo ride: exactly one trip_passengers row, created immediately (no matching
     * attempt -- that's ride_share-only). The trip_passengers row (and its pickup/dropoff
     * stops) is the sole source of truth for customer-facing data -- trips itself only
     * carries vehicle-trip-level state (status, rider, vehicle, route).
     *
     * @param  array<string, mixed>  $data
     */
    private function bookRide(User $user, array $data, Point $pickup, Point $dropoff, Zone $zone, PricingRule $pricingRule, CarbonImmutable $requestedAt): TripPassenger
    {
        $distanceKm = (float) $data['distance_km'];
        $durationMinutes = $this->checkout->durationMinutes($distanceKm);
        $fare = $this->checkout->calculateFare($pricingRule, $zone, (int) $data['vehicle_type_id'], $distanceKm, $durationMinutes, $requestedAt);
        $promoResult = $this->checkout->resolvePromoDiscount($data['promo_code'] ?? null, $user, $zone, (int) $data['vehicle_type_id'], $fare['fare']);
        $totalBeforeRounding = round($fare['fare'] - $promoResult['discount'], 2);
        $total = $this->checkout->roundFare($totalBeforeRounding);
        $capacity = (int) VehicleType::query()->findOrFail((int) $data['vehicle_type_id'])->capacity;

        [$trip, $passenger] = DB::transaction(function () use ($user, $data, $zone, $pickup, $dropoff, $distanceKm, $durationMinutes, $fare, $promoResult, $totalBeforeRounding, $total, $capacity, $requestedAt): array {
            $trip = Trip::query()->create([
                'trip_number' => $this->generateTripNumber(),
                'vehicle_type_id' => $data['vehicle_type_id'],
                'zone_id' => $zone->id,
                'type' => 'ride',
                'status' => 'requested',
                'available_seats' => max($capacity - 1, 0),
                'passenger_count' => 1,
                'requested_at' => $requestedAt,
            ]);

            $passenger = TripPassenger::query()->create([
                'trip_id' => $trip->id,
                'customer_id' => $user->id,
                'seats_requested' => 1,
                'status' => 'requested',
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'promo_code_id' => $promoResult['promo']?->id,
                'requested_at' => $requestedAt,
            ]);

            TripFareBreakdown::query()->create([
                'trip_id' => $trip->id,
                'passenger_id' => $passenger->id,
                'customer_id' => $user->id,
                'base_fare' => $fare['base_fare'],
                'distance_fare' => $fare['distance_fare'],
                'time_fare' => $fare['time_fare'],
                'surge_multiplier' => $fare['surge_multiplier'],
                'surge_amount' => $fare['surge_amount'],
                'discount_amount' => $promoResult['discount'],
                'estimated_fare' => $total,
                'estimated_fare_before_rounding' => $totalBeforeRounding,
                'currency_code' => $zone->currency_code,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
            ]);

            TripStop::query()->create([
                'trip_id' => $trip->id,
                'trip_passenger_id' => $passenger->id,
                'stop_type' => 'pickup',
                'seats_delta' => 1,
                'sequence' => 1,
                'location' => $pickup,
                'address' => $data['pickup_address'] ?? null,
            ]);

            TripStop::query()->create([
                'trip_id' => $trip->id,
                'trip_passenger_id' => $passenger->id,
                'stop_type' => 'dropoff',
                'seats_delta' => -1,
                'sequence' => 2,
                'location' => $dropoff,
                'address' => $data['dropoff_address'] ?? null,
            ]);

            if ($promoResult['promo'] !== null) {
                PromoCodeRedemption::query()->create([
                    'promo_code_id' => $promoResult['promo']->id,
                    'user_id' => $user->id,
                    'trip_id' => $trip->id,
                    'discount_amount' => $promoResult['discount'],
                    'redeemed_at' => now(),
                ]);
            }

            return [$trip, $passenger];
        });

        if (! $requestedAt->isFuture()) {
            $trip->update(['status' => 'searching']);

            try {
                $this->dispatchToNearbyRiders($trip, $pickup, (int) $data['vehicle_type_id'], $data['pickup_address'] ?? null, $total, $zone->currency_code);
            } catch (Throwable $e) {
                Log::error('trip.dispatch_failed', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $passenger->load(['customer', 'fareBreakdown', 'trip.vehicleType', 'stops']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bookRideShare(User $user, array $data, Point $pickup, Point $dropoff, Zone $zone, PricingRule $pricingRule, CarbonImmutable $requestedAt): TripPassenger
    {
        $seatsRequested = (int) ($data['seats_requested'] ?? 1);

        // Ride-share is instant-only -- there's no scheduling endpoint for it (see
        // ScheduleTripRequest, which only allows type in:ride,delivery), so there's always an
        // ongoing trip to potentially merge into.
        $matched = $this->rideShareMatching->match(
            $user,
            $pickup,
            $data['pickup_address'] ?? null,
            $dropoff,
            $data['dropoff_address'] ?? null,
            (int) $data['vehicle_type_id'],
            $seatsRequested,
            $zone,
            $pricingRule,
        );

        if ($matched !== null) {
            return $matched->load(['customer', 'fareBreakdown', 'trip.rider.riderProfile', 'trip.vehicleType', 'stops']);
        }

        // No compatible ongoing trip: start a brand-new ride-share trip, seeded by this
        // first passenger. It's dispatched to nearby idle drivers exactly like a solo ride
        // (reusing dispatchToNearbyRiders/RiderTripService::accept unchanged) -- as further
        // passengers join mid-route, they attach via RideShareMatchingService instead.
        $distanceKm = (float) $data['distance_km'];
        $durationMinutes = $this->checkout->durationMinutes($distanceKm);
        $fare = $this->checkout->calculateRideShareFare($pricingRule, $zone, (int) $data['vehicle_type_id'], $distanceKm, $durationMinutes, $requestedAt);
        $capacity = (int) VehicleType::query()->findOrFail((int) $data['vehicle_type_id'])->capacity;

        [$trip, $passenger] = DB::transaction(function () use ($user, $data, $zone, $pickup, $dropoff, $distanceKm, $durationMinutes, $fare, $capacity, $seatsRequested, $requestedAt): array {
            $trip = Trip::query()->create([
                'trip_number' => $this->generateTripNumber(),
                'vehicle_type_id' => $data['vehicle_type_id'],
                'zone_id' => $zone->id,
                'type' => 'ride_share',
                'status' => 'requested',
                'available_seats' => max($capacity - $seatsRequested, 0),
                'passenger_count' => 1,
                'requested_at' => $requestedAt,
            ]);

            $passenger = TripPassenger::query()->create([
                'trip_id' => $trip->id,
                'customer_id' => $user->id,
                'seats_requested' => $seatsRequested,
                'status' => 'requested',
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'requested_at' => $requestedAt,
            ]);

            TripFareBreakdown::query()->create([
                'trip_id' => $trip->id,
                'passenger_id' => $passenger->id,
                'customer_id' => $user->id,
                'base_fare' => $fare['base_fare'],
                'distance_fare' => $fare['distance_fare'],
                'time_fare' => $fare['time_fare'],
                'surge_multiplier' => $fare['surge_multiplier'],
                'surge_amount' => $fare['surge_amount'],
                'discount_percentage' => $fare['discount_percentage'],
                'estimated_fare' => $fare['fare'],
                // calculateRideShareFare() doesn't round to nearest 500 the way bookRide()
                // does -- recorded as-is (equal to estimated_fare) rather than faking a
                // rounding step that doesn't actually happen here.
                'estimated_fare_before_rounding' => $fare['fare'],
                'currency_code' => $zone->currency_code,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
            ]);

            TripStop::query()->create([
                'trip_id' => $trip->id,
                'trip_passenger_id' => $passenger->id,
                'stop_type' => 'pickup',
                'seats_delta' => $seatsRequested,
                'sequence' => 1,
                'location' => $pickup,
                'address' => $data['pickup_address'] ?? null,
            ]);

            TripStop::query()->create([
                'trip_id' => $trip->id,
                'trip_passenger_id' => $passenger->id,
                'stop_type' => 'dropoff',
                'seats_delta' => -$seatsRequested,
                'sequence' => 2,
                'location' => $dropoff,
                'address' => $data['dropoff_address'] ?? null,
            ]);

            return [$trip, $passenger];
        });

        $trip->update(['status' => 'searching']);

        try {
            $this->dispatchToNearbyRiders($trip, $pickup, (int) $data['vehicle_type_id'], $data['pickup_address'] ?? null, $fare['fare'], $zone->currency_code);
        } catch (Throwable $e) {
            Log::error('trip.dispatch_failed', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $passenger->load(['customer', 'fareBreakdown', 'trip.vehicleType', 'stops']);
    }

    /**
     * A solo delivery: exactly one delivery_details row, created immediately (no matching
     * attempt -- that's delivery_share-only). Mirrors bookRide(): delivery_details (and its
     * stops) is the sole source of truth for customer-facing data.
     *
     * @param  array<string, mixed>  $data
     */
    private function bookDelivery(User $user, array $data, Point $pickup, Point $dropoff, Zone $zone, PricingRule $pricingRule, CarbonImmutable $requestedAt): DeliveryDetails
    {
        $distanceKm = (float) $data['distance_km'];
        $durationMinutes = $this->checkout->durationMinutes($distanceKm);
        $fare = $this->checkout->calculateFare($pricingRule, $zone, (int) $data['vehicle_type_id'], $distanceKm, $durationMinutes, $requestedAt);
        $promoResult = $this->checkout->resolvePromoDiscount($data['promo_code'] ?? null, $user, $zone, (int) $data['vehicle_type_id'], $fare['fare']);
        $total = $this->checkout->roundFare(round($fare['fare'] - $promoResult['discount'], 2));

        [$trip, $delivery] = DB::transaction(function () use ($user, $data, $zone, $pickup, $dropoff, $distanceKm, $durationMinutes, $fare, $promoResult, $total, $requestedAt): array {
            $trip = Trip::query()->create([
                'trip_number' => $this->generateTripNumber(),
                'vehicle_type_id' => $data['vehicle_type_id'],
                'zone_id' => $zone->id,
                'type' => 'delivery',
                'status' => 'requested',
                'passenger_count' => 1,
                'requested_at' => $requestedAt,
            ]);

            $delivery = DeliveryDetails::query()->create([
                'trip_id' => $trip->id,
                'sender_id' => $user->id,
                'status' => 'requested',
                'pickup_location' => $pickup,
                'pickup_address' => $data['pickup_address'] ?? null,
                'dropoff_location' => $dropoff,
                'dropoff_address' => $data['dropoff_address'] ?? null,
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'base_fare_amount' => $fare['base_fare'],
                'promo_code_id' => $promoResult['promo']?->id,
                'discount_amount' => $promoResult['discount'],
                'estimated_fare' => $total,
                'currency_code' => $zone->currency_code,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'requested_at' => $requestedAt,
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'],
                'package_description' => $data['package_description'] ?? null,
                'package_size' => $data['package_size'] ?? 'small',
                'package_weight_kg' => $data['package_weight_kg'] ?? null,
                'requires_signature' => $data['requires_signature'] ?? false,
            ]);

            DeliveryStop::query()->create([
                'trip_id' => $trip->id,
                'delivery_details_id' => $delivery->id,
                'stop_type' => 'pickup',
                'sequence' => 1,
                'location' => $pickup,
                'address' => $data['pickup_address'] ?? null,
            ]);

            DeliveryStop::query()->create([
                'trip_id' => $trip->id,
                'delivery_details_id' => $delivery->id,
                'stop_type' => 'dropoff',
                'sequence' => 2,
                'location' => $dropoff,
                'address' => $data['dropoff_address'] ?? null,
            ]);

            if ($promoResult['promo'] !== null) {
                PromoCodeRedemption::query()->create([
                    'promo_code_id' => $promoResult['promo']->id,
                    'user_id' => $user->id,
                    'trip_id' => $trip->id,
                    'discount_amount' => $promoResult['discount'],
                    'redeemed_at' => now(),
                ]);
            }

            return [$trip, $delivery];
        });

        if (! $requestedAt->isFuture()) {
            $trip->update(['status' => 'searching']);

            try {
                $this->dispatchToNearbyRiders($trip, $pickup, (int) $data['vehicle_type_id'], $data['pickup_address'] ?? null, $total, $zone->currency_code);
            } catch (Throwable $e) {
                Log::error('trip.dispatch_failed', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $delivery->load(['sender', 'trip.vehicleType', 'stops']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function bookDeliveryShare(User $user, array $data, Point $pickup, Point $dropoff, Zone $zone, PricingRule $pricingRule, CarbonImmutable $requestedAt): DeliveryDetails
    {
        $weightKg = (float) ($data['package_weight_kg'] ?? 0);

        if (! $requestedAt->isFuture()) {
            $matched = $this->deliveryPoolingMatching->match(
                $user,
                $pickup,
                $data['pickup_address'] ?? null,
                $dropoff,
                $data['dropoff_address'] ?? null,
                (int) $data['vehicle_type_id'],
                $weightKg,
                $zone,
                $pricingRule,
                $data,
            );

            if ($matched !== null) {
                return $matched->load(['sender', 'trip.rider.riderProfile', 'trip.vehicleType', 'stops']);
            }
        }

        // No compatible ongoing trip: start a brand-new pooled-delivery trip, seeded by
        // this first delivery. Dispatched to nearby idle drivers exactly like a solo
        // delivery -- as further deliveries join mid-route, they attach via
        // DeliveryPoolingMatchingService instead.
        $distanceKm = (float) $data['distance_km'];
        $durationMinutes = $this->checkout->durationMinutes($distanceKm);
        $fare = $this->checkout->calculateDeliveryShareFare($pricingRule, $zone, (int) $data['vehicle_type_id'], $distanceKm, $durationMinutes, $requestedAt);
        $vehicleType = VehicleType::query()->findOrFail((int) $data['vehicle_type_id']);
        $capacity = (float) ($vehicleType->max_cargo_weight_kg ?? 0);

        if ($capacity <= 0) {
            throw ValidationException::withMessages([
                'vehicle_type_id' => 'This vehicle type does not support delivery pooling.',
            ]);
        }

        [$trip, $delivery] = DB::transaction(function () use ($user, $data, $zone, $pickup, $dropoff, $distanceKm, $durationMinutes, $fare, $capacity, $weightKg, $requestedAt): array {
            $trip = Trip::query()->create([
                'trip_number' => $this->generateTripNumber(),
                'vehicle_type_id' => $data['vehicle_type_id'],
                'zone_id' => $zone->id,
                'type' => 'delivery_share',
                'status' => 'requested',
                'available_cargo_weight_kg' => max($capacity - $weightKg, 0),
                'passenger_count' => 1,
                'requested_at' => $requestedAt,
            ]);

            $delivery = DeliveryDetails::query()->create([
                'trip_id' => $trip->id,
                'sender_id' => $user->id,
                'status' => 'requested',
                'pickup_location' => $pickup,
                'pickup_address' => $data['pickup_address'] ?? null,
                'dropoff_location' => $dropoff,
                'dropoff_address' => $data['dropoff_address'] ?? null,
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'base_fare_amount' => $fare['base_fare'],
                'discount_percentage' => $fare['discount_percentage'],
                'estimated_fare' => $fare['fare'],
                'currency_code' => $zone->currency_code,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
                'requested_at' => $requestedAt,
                'recipient_name' => $data['recipient_name'],
                'recipient_phone' => $data['recipient_phone'],
                'package_description' => $data['package_description'] ?? null,
                'package_size' => $data['package_size'] ?? 'small',
                'package_weight_kg' => $data['package_weight_kg'] ?? null,
                'requires_signature' => $data['requires_signature'] ?? false,
            ]);

            DeliveryStop::query()->create([
                'trip_id' => $trip->id,
                'delivery_details_id' => $delivery->id,
                'stop_type' => 'pickup',
                'sequence' => 1,
                'location' => $pickup,
                'address' => $data['pickup_address'] ?? null,
            ]);

            DeliveryStop::query()->create([
                'trip_id' => $trip->id,
                'delivery_details_id' => $delivery->id,
                'stop_type' => 'dropoff',
                'sequence' => 2,
                'location' => $dropoff,
                'address' => $data['dropoff_address'] ?? null,
            ]);

            return [$trip, $delivery];
        });

        if (! $requestedAt->isFuture()) {
            $trip->update(['status' => 'searching']);

            try {
                $this->dispatchToNearbyRiders($trip, $pickup, (int) $data['vehicle_type_id'], $data['pickup_address'] ?? null, $fare['fare'], $zone->currency_code);
            } catch (Throwable $e) {
                Log::error('trip.dispatch_failed', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $delivery->load(['sender', 'trip.vehicleType', 'stops']);
    }

    /**
     * Every trip type now settles through cancelPassenger()/cancelDelivery() instead --
     * kept as a guarded stub (rather than removed) so any client still hitting the old
     * trip-id-based cancel endpoint gets a clear validation error instead of a crash.
     */
    public function cancel(User $user, int $tripId, ?int $cancellationReasonId): never
    {
        throw ValidationException::withMessages([
            'type' => 'Use the passenger or delivery cancel endpoint to cancel this trip.',
        ]);
    }

    /**
     * Cancel this customer's own seat on a ride or ride-share trip, not the whole vehicle
     * trip. No cancellation fee is charged. Any of their stops not yet reached are removed
     * and their seats are released back to the trip immediately.
     *
     * If they're the trip's only passenger and no driver has accepted yet, the whole
     * vehicle trip is cancelled too -- there's no one else on it and no driver-facing
     * lifecycle (accept/arrive/start) to keep alive.
     */
    public function cancelPassenger(User $user, int $tripPassengerId, ?int $cancellationReasonId): TripPassenger
    {
        $passenger = TripPassenger::query()
            ->where('customer_id', $user->id)
            ->where('id', $tripPassengerId)
            ->with('trip')
            ->firstOrFail();

        if (in_array($passenger->status, ['dropped_off', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This ride can no longer be cancelled.',
            ]);
        }

        DB::transaction(function () use ($passenger, $user, $cancellationReasonId): void {
            $trip = $passenger->trip;

            $passenger->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason_id' => $cancellationReasonId,
            ]);

            $unvisitedStopIds = $passenger->stops()->whereNull('arrived_at')->pluck('id');

            if ($unvisitedStopIds->isNotEmpty()) {
                TripStop::query()->whereIn('id', $unvisitedStopIds)->delete();
            }

            if ($trip->status === 'in_progress') {
                $trip->increment('available_seats', $passenger->seats_requested);
                $trip->decrement('passenger_count');
            } elseif ($trip->passenger_count <= 1 && ! in_array($trip->status, ['completed', 'cancelled'], true)) {
                $trip->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $user->id,
                    'cancellation_reason_id' => $cancellationReasonId,
                ]);
            }
        });

        return $passenger->refresh()->load(['trip', 'stops']);
    }

    /**
     * Cancel this sender's own delivery on a delivery or pooled-delivery trip -- mirrors
     * cancelPassenger() exactly, operating on delivery_details/delivery_stops instead.
     */
    public function cancelDelivery(User $user, int $deliveryDetailsId, ?int $cancellationReasonId): DeliveryDetails
    {
        $delivery = DeliveryDetails::query()
            ->where('sender_id', $user->id)
            ->where('id', $deliveryDetailsId)
            ->with('trip')
            ->firstOrFail();

        if (in_array($delivery->status, ['dropped_off', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This delivery can no longer be cancelled.',
            ]);
        }

        DB::transaction(function () use ($delivery, $user, $cancellationReasonId): void {
            $trip = $delivery->trip;

            $delivery->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason_id' => $cancellationReasonId,
            ]);

            $unvisitedStopIds = $delivery->stops()->whereNull('arrived_at')->pluck('id');

            if ($unvisitedStopIds->isNotEmpty()) {
                DeliveryStop::query()->whereIn('id', $unvisitedStopIds)->delete();
            }

            if ($trip->status === 'in_progress') {
                $trip->increment('available_cargo_weight_kg', (float) $delivery->package_weight_kg);
                $trip->decrement('passenger_count');
            } elseif ($trip->passenger_count <= 1 && ! in_array($trip->status, ['completed', 'cancelled'], true)) {
                $trip->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $user->id,
                    'cancellation_reason_id' => $cancellationReasonId,
                ]);
            }
        });

        return $delivery->refresh()->load(['trip', 'stops']);
    }

    /**
     * Every trip type now rates through ratePassengerDriver()/rateDeliveryDriver() instead
     * -- kept as a guarded stub for the same reason as cancel().
     *
     * @param  array<string, mixed>  $data
     */
    public function rateRider(User $user, int $tripId, array $data): never
    {
        throw ValidationException::withMessages([
            'type' => 'Use the passenger or delivery rate endpoint to rate your driver for this trip.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function ratePassengerDriver(User $user, int $tripPassengerId, array $data): Rating
    {
        $passenger = TripPassenger::query()
            ->where('customer_id', $user->id)
            ->where('id', $tripPassengerId)
            ->with('trip')
            ->firstOrFail();

        if ($passenger->status !== 'dropped_off') {
            throw ValidationException::withMessages([
                'trip' => 'Only completed rides can be rated.',
            ]);
        }

        if ($passenger->trip->rider_id === null) {
            throw ValidationException::withMessages([
                'trip' => 'This trip has no assigned rider to rate.',
            ]);
        }

        if (Rating::query()->where('trip_id', $passenger->trip_id)->where('rater_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'trip' => 'You have already rated this ride.',
            ]);
        }

        return Rating::query()->create([
            'trip_id' => $passenger->trip_id,
            'rater_id' => $user->id,
            'ratee_id' => $passenger->trip->rider_id,
            'rater_role' => 'customer',
            'score' => $data['score'],
            'comment' => $data['comment'] ?? null,
            'tags' => $data['tags'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function rateDeliveryDriver(User $user, int $deliveryDetailsId, array $data): Rating
    {
        $delivery = DeliveryDetails::query()
            ->where('sender_id', $user->id)
            ->where('id', $deliveryDetailsId)
            ->with('trip')
            ->firstOrFail();

        if ($delivery->status !== 'dropped_off') {
            throw ValidationException::withMessages([
                'trip' => 'Only completed deliveries can be rated.',
            ]);
        }

        if ($delivery->trip->rider_id === null) {
            throw ValidationException::withMessages([
                'trip' => 'This trip has no assigned rider to rate.',
            ]);
        }

        if (Rating::query()->where('trip_id', $delivery->trip_id)->where('rater_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'trip' => 'You have already rated this delivery.',
            ]);
        }

        return Rating::query()->create([
            'trip_id' => $delivery->trip_id,
            'rater_id' => $user->id,
            'ratee_id' => $delivery->trip->rider_id,
            'rater_role' => 'customer',
            'score' => $data['score'],
            'comment' => $data['comment'] ?? null,
            'tags' => $data['tags'] ?? null,
        ]);
    }

    /**
     * A future-scheduled trip becoming due always has exactly one seed passenger/delivery --
     * matching into an ongoing trip only ever happens for immediate (non-future) requests --
     * so its pickup stop and fare are this sole item's.
     */
    public function dispatchDueTrip(Trip $trip): void
    {
        $isRide = in_array($trip->type, ['ride', 'ride_share'], true);

        $item = $isRide
            ? TripPassenger::query()->where('trip_id', $trip->id)->with('fareBreakdown')->oldest('requested_at')->first()
            : DeliveryDetails::query()->where('trip_id', $trip->id)->oldest('requested_at')->first();

        $pickupStop = $isRide
            ? TripStop::query()->where('trip_id', $trip->id)->where('stop_type', 'pickup')->orderBy('sequence')->first()
            : DeliveryStop::query()->where('trip_id', $trip->id)->where('stop_type', 'pickup')->orderBy('sequence')->first();

        if ($item === null || $pickupStop?->location === null) {
            return;
        }

        // Ride/ride_share's fare lives on the passenger's fare breakdown now; delivery keeps
        // it on the delivery record directly.
        $estimatedFare = $item instanceof TripPassenger ? $item->fareBreakdown?->estimated_fare : $item->estimated_fare;
        $currencyCode = $item instanceof TripPassenger ? $item->fareBreakdown?->currency_code : $item->currency_code;

        if ($estimatedFare === null || $currencyCode === null) {
            return;
        }

        $this->dispatchToNearbyRiders(
            $trip,
            $pickupStop->location,
            (int) $trip->vehicle_type_id,
            $pickupStop->address,
            (float) $estimatedFare,
            $currencyCode,
        );
    }

    private function dispatchToNearbyRiders(Trip $trip, Point $pickup, int $vehicleTypeId, ?string $pickupAddress, float $estimatedFare, string $currencyCode): void
    {
        $radiusMeters = (float) Configuration::get('dispatch_radius_km', 5) * 1000;

        $riderProfiles = RiderProfile::query()
            ->where('availability_status', 'online')
            ->whereNotNull('current_location')
            ->whereHas('vehicle', fn ($query) => $query->where('vehicle_type_id', $vehicleTypeId)->where('status', 'approved'))
            ->where(ST::distanceSphere('current_location', $pickup), '<=', $radiusMeters)
            ->with('user.devices')
            ->get();

        $tokens = $riderProfiles
            ->flatMap(fn (RiderProfile $riderProfile) => $riderProfile->user->devices)
            ->filter(fn (UserDevice $device): bool => $device->active && filled($device->fcm_token))
            ->map(fn (UserDevice $device): string => (string) $device->fcm_token)
            ->unique()
            ->values()
            ->all();

        $body = $pickupAddress
            ? "New {$trip->type} request near {$pickupAddress}"
            : "New {$trip->type} request nearby";

        $this->pushGateway->sendToTokens(
            array_values($tokens),
            'New trip request',
            $body,
            [
                'trip_id' => (string) $trip->id,
                'type' => $trip->type,
                'pickup_latitude' => (string) $pickup->getLatitude(),
                'pickup_longitude' => (string) $pickup->getLongitude(),
                'estimated_fare' => (string) $estimatedFare,
                'currency_code' => $currencyCode,
            ],
        );
    }

    private function generateTripNumber(): string
    {
        do {
            $tripNumber = 'TRP-'.now()->format('ymd').strtoupper(Str::random(6));
        } while (Trip::query()->where('trip_number', $tripNumber)->exists());

        return $tripNumber;
    }
}
