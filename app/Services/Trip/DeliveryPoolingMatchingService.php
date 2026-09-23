<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\DeliveryDetails;
use App\Models\DeliveryStop;
use App\Models\PricingRule;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Zone;
use App\Services\Checkout\CheckoutService;
use App\Services\Push\FcmGateway;
use App\Services\Routing\Contracts\RoutingGateway;
use App\Services\Routing\RouteResult;
use App\Support\Geo;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Finds an ongoing pooled-delivery trip a new delivery request can merge into, and attaches
 * it as an extra delivery. Structurally parallel to RideShareMatchingService, but the
 * capacity dimension is cargo weight (kg) rather than seats.
 *
 * Matching runs three checks, cheapest first, against each nearby ongoing delivery_share
 * trip:
 *   1. direction   - bearing pre-filter, no routing call
 *   2. capacity    - does the vehicle have enough remaining cargo weight capacity across
 *                    the span this delivery would ride, given everything already scheduled
 *   3. detour      - how much extra driving time/distance would inserting this delivery's
 *                    pickup+dropoff add to the vehicle's remaining route
 *
 * Same known v1 simplification as RideShareMatchingService: a new delivery's pickup and
 * dropoff are only tried as an adjacent pair inserted at each gap in the remaining stop
 * sequence, not split apart.
 */
readonly class DeliveryPoolingMatchingService
{
    public function __construct(
        private RoutingGateway $routing,
        private CheckoutService $checkout,
        private FcmGateway $pushGateway,
    ) {}

    /**
     * @param  array<string, mixed>  $deliveryData
     */
    public function match(
        User $sender,
        Point $pickup,
        ?string $pickupAddress,
        Point $dropoff,
        ?string $dropoffAddress,
        int $vehicleTypeId,
        float $weightKg,
        Zone $zone,
        PricingRule $pricingRule,
        array $deliveryData,
    ): ?DeliveryDetails {
        $best = null;

        foreach ($this->shortlistCandidates($pickup, $vehicleTypeId, $weightKg) as $trip) {
            $evaluation = $this->evaluateCandidate($trip, $pickup, $dropoff, $weightKg);

            if ($evaluation === null) {
                continue;
            }

            if ($best === null || $evaluation['detour_minutes'] < $best['detour_minutes']) {
                $best = [...$evaluation, 'trip' => $trip];
            }
        }

        if ($best === null) {
            return null;
        }

        return $this->attach($sender, $pickup, $pickupAddress, $dropoff, $dropoffAddress, $weightKg, $zone, $pricingRule, $deliveryData, $best);
    }

    /**
     * @return Collection<int, Trip>
     */
    private function shortlistCandidates(Point $pickup, int $vehicleTypeId, float $weightKg): Collection
    {
        $radiusMeters = (float) Configuration::get('delivery_share_candidate_search_radius_km', 5) * 1000;
        $maxCandidates = (int) Configuration::get('delivery_share_max_candidates', 5);

        $candidates = Trip::query()
            ->where('type', 'delivery_share')
            ->where('status', 'in_progress')
            ->where('vehicle_type_id', $vehicleTypeId)
            ->where('available_cargo_weight_kg', '>=', $weightKg)
            ->whereHas('rider.riderProfile', function ($query) use ($pickup, $radiusMeters) {
                $query->whereNotNull('current_location')
                    ->where(ST::distanceSphere('current_location', $pickup), '<=', $radiusMeters);
            })
            ->with([
                'rider.riderProfile',
                'vehicleType',
                'deliveryStops' => fn ($query) => $query->whereNull('arrived_at')->orderBy('sequence'),
            ])
            ->get();

        return $candidates
            ->sortBy(fn (Trip $trip) => Geo::haversineKm($trip->rider->riderProfile->current_location, $pickup))
            ->take($maxCandidates)
            ->values();
    }

    /**
     * @return array{gap: int, route: RouteResult, detour_minutes: int, detour_km: float, remaining_stops: Collection<int, DeliveryStop>, driver_location: Point}|null
     */
    private function evaluateCandidate(Trip $trip, Point $pickup, Point $dropoff, float $weightKg): ?array
    {
        $remainingStops = $trip->deliveryStops;

        if ($remainingStops->isEmpty()) {
            // Shouldn't happen for an in_progress pooled-delivery trip (it should have
            // auto-completed once its last scheduled stop was reached), but guard anyway.
            return null;
        }

        $driverLocation = $trip->rider->riderProfile->current_location;

        if (! $this->passesDirectionCheck($driverLocation, $remainingStops->last()->location, $pickup)) {
            return null;
        }

        $capacity = (float) ($trip->vehicleType->max_cargo_weight_kg ?? 0);
        $occupiedNow = $capacity - (float) $trip->available_cargo_weight_kg;
        $occupancyAtGap = $this->occupancyPrefix($remainingStops, $occupiedNow);

        $baseline = $this->routing->computeRoute([
            $driverLocation,
            ...array_values($remainingStops->pluck('location')->all()),
        ]);

        $maxDetourMinutes = (float) Configuration::get('delivery_share_max_detour_minutes', 10);
        $maxDetourKm = (float) Configuration::get('delivery_share_max_detour_km', 5);

        $best = null;

        for ($gap = 0; $gap <= $remainingStops->count(); $gap++) {
            if ($capacity < $occupancyAtGap[$gap] + $weightKg) {
                continue;
            }

            $trialWaypoints = [
                $driverLocation,
                ...array_values($remainingStops->slice(0, $gap)->pluck('location')->all()),
                $pickup,
                $dropoff,
                ...array_values($remainingStops->slice($gap)->pluck('location')->all()),
            ];

            $trial = $this->routing->computeRoute($trialWaypoints);

            $detourMinutes = $trial->durationMinutes - $baseline->durationMinutes;
            $detourKm = $trial->distanceKm - $baseline->distanceKm;

            if ($detourMinutes > $maxDetourMinutes || $detourKm > $maxDetourKm) {
                continue;
            }

            if ($best === null || $detourMinutes < $best['detour_minutes']) {
                $best = [
                    'gap' => $gap,
                    'route' => $trial,
                    'detour_minutes' => $detourMinutes,
                    'detour_km' => $detourKm,
                    'remaining_stops' => $remainingStops,
                    'driver_location' => $driverLocation,
                ];
            }
        }

        return $best;
    }

    /**
     * Cheap pre-filter, no routing call: rejects a candidate whose overall direction of
     * travel (driver -> its last remaining stop) diverges too far from the bearing to the
     * new pickup point.
     */
    private function passesDirectionCheck(Point $driverLocation, Point $finalRemainingStop, Point $pickup): bool
    {
        $maxDeviation = (float) Configuration::get('delivery_share_max_bearing_deviation_degrees', 100);

        $overallBearing = Geo::bearingDegrees($driverLocation, $finalRemainingStop);
        $requestBearing = Geo::bearingDegrees($driverLocation, $pickup);

        return Geo::bearingDifference($overallBearing, $requestBearing) <= $maxDeviation;
    }

    /**
     * Running cargo-weight occupancy (kg) at each gap boundary, starting from the vehicle's
     * current occupancy. DeliveryStop has no seats_delta column -- weight delta is derived
     * from the linked delivery's package_weight_kg, positive at pickup and negative at
     * dropoff.
     *
     * @param  Collection<int, DeliveryStop>  $remainingStops
     * @return list<float>
     */
    private function occupancyPrefix(Collection $remainingStops, float $occupiedNow): array
    {
        $prefix = [$occupiedNow];

        foreach ($remainingStops as $stop) {
            $weight = (float) ($stop->deliveryDetails->package_weight_kg ?? 0);
            $delta = $stop->stop_type === 'pickup' ? $weight : -$weight;
            $prefix[] = end($prefix) + $delta;
        }

        return $prefix;
    }

    /**
     * @param  array<string, mixed>  $deliveryData
     * @param  array{gap: int, route: RouteResult, detour_minutes: float, detour_km: float, remaining_stops: Collection<int, DeliveryStop>, driver_location: Point, trip: Trip}  $winner
     */
    private function attach(
        User $sender,
        Point $pickup,
        ?string $pickupAddress,
        Point $dropoff,
        ?string $dropoffAddress,
        float $weightKg,
        Zone $zone,
        PricingRule $pricingRule,
        array $deliveryData,
        array $winner,
    ): ?DeliveryDetails {
        /** @var Trip $trip */
        $trip = $winner['trip'];

        // Atomic claim, same pattern as the ride-share seat claim: if another request
        // already consumed the cargo capacity since we evaluated this candidate, this
        // affects 0 rows and we report "no match" rather than retrying a stale plan
        // against a second-best candidate.
        $claimed = Trip::query()
            ->where('id', $trip->id)
            ->where('available_cargo_weight_kg', '>=', $weightKg)
            ->decrement('available_cargo_weight_kg', $weightKg, [
                'passenger_count' => DB::raw('passenger_count + 1'),
                'route_polyline' => $winner['route']->polyline,
                'route_distance_km' => $winner['route']->distanceKm,
                'route_duration_minutes' => $winner['route']->durationMinutes,
            ]);

        if ($claimed === 0) {
            return null;
        }

        $deliveryLeg = $winner['route']->legs[$winner['gap'] + 1] ?? ['distance_km' => 0.0, 'duration_minutes' => 0];

        $fare = $this->checkout->calculateDeliveryShareFare(
            $pricingRule,
            $zone,
            (int) $trip->vehicle_type_id,
            (float) $deliveryLeg['distance_km'],
            (int) $deliveryLeg['duration_minutes'],
            now(),
        );

        return DB::transaction(function () use ($trip, $sender, $pickup, $pickupAddress, $dropoff, $dropoffAddress, $zone, $deliveryLeg, $fare, $deliveryData, $winner) {
            $delivery = DeliveryDetails::query()->create([
                'trip_id' => $trip->id,
                'sender_id' => $sender->id,
                'status' => 'matched',
                'pickup_location' => $pickup,
                'pickup_address' => $pickupAddress,
                'dropoff_location' => $dropoff,
                'dropoff_address' => $dropoffAddress,
                'distance_km' => $deliveryLeg['distance_km'],
                'duration_minutes' => $deliveryLeg['duration_minutes'],
                'base_fare_amount' => $fare['base_fare'],
                'discount_percentage' => $fare['discount_percentage'],
                'estimated_fare' => $fare['fare'],
                'currency_code' => $zone->currency_code,
                'requested_at' => now(),
                'matched_at' => now(),
                'recipient_name' => $deliveryData['recipient_name'],
                'recipient_phone' => $deliveryData['recipient_phone'],
                'package_description' => $deliveryData['package_description'] ?? null,
                'package_size' => $deliveryData['package_size'] ?? 'small',
                'package_weight_kg' => $deliveryData['package_weight_kg'] ?? null,
                'requires_signature' => $deliveryData['requires_signature'] ?? false,
            ]);

            $this->insertStopsAtGap($trip, $delivery, $winner['gap'], $winner['remaining_stops'], $pickup, $pickupAddress, $dropoff, $dropoffAddress);

            $this->notifyDriverOfNewDelivery($trip, $delivery);
            $this->notifyCustomerOfMatch($trip, $sender);

            return $delivery;
        });
    }

    /**
     * @param  Collection<int, DeliveryStop>  $remainingStops
     */
    private function insertStopsAtGap(
        Trip $trip,
        DeliveryDetails $delivery,
        int $gap,
        Collection $remainingStops,
        Point $pickup,
        ?string $pickupAddress,
        Point $dropoff,
        ?string $dropoffAddress,
    ): void {
        $arrivedCount = DeliveryStop::query()->where('trip_id', $trip->id)->whereNotNull('arrived_at')->count();

        $before = $remainingStops->slice(0, $gap)->values();
        $after = $remainingStops->slice($gap)->values();

        // Two-phase renumber: bump every remaining stop out of the way first, so
        // reassigning final sequence numbers never collides with the unique(trip_id,
        // sequence) constraint while stops are shifting past each other.
        DeliveryStop::query()
            ->where('trip_id', $trip->id)
            ->whereIn('id', $remainingStops->pluck('id'))
            ->update(['sequence' => DB::raw('sequence + 1000')]);

        $sequence = $arrivedCount + 1;

        foreach ($before as $stop) {
            $stop->update(['sequence' => $sequence++]);
        }

        DeliveryStop::query()->create([
            'trip_id' => $trip->id,
            'delivery_details_id' => $delivery->id,
            'stop_type' => 'pickup',
            'sequence' => $sequence++,
            'location' => $pickup,
            'address' => $pickupAddress,
        ]);

        DeliveryStop::query()->create([
            'trip_id' => $trip->id,
            'delivery_details_id' => $delivery->id,
            'stop_type' => 'dropoff',
            'sequence' => $sequence++,
            'location' => $dropoff,
            'address' => $dropoffAddress,
        ]);

        foreach ($after as $stop) {
            $stop->update(['sequence' => $sequence++]);
        }
    }

    private function notifyDriverOfNewDelivery(Trip $trip, DeliveryDetails $delivery): void
    {
        $driver = $trip->rider;

        if ($driver === null) {
            return;
        }

        $tokens = $driver->devices
            ->filter(fn (UserDevice $device): bool => $device->active && filled($device->fcm_token))
            ->map(fn (UserDevice $device): string => (string) $device->fcm_token)
            ->unique()
            ->values()
            ->all();

        try {
            $this->pushGateway->sendToTokens(
                array_values($tokens),
                'New delivery added to your route',
                'A new delivery has been added to your pooled-delivery trip. Your route has been updated.',
                [
                    'trip_id' => (string) $trip->id,
                    'delivery_details_id' => (string) $delivery->id,
                ],
            );
        } catch (Throwable $e) {
            Log::error('delivery_share.driver_notification_failed', ['trip_id' => $trip->id, 'error' => $e->getMessage()]);
        }
    }

    private function notifyCustomerOfMatch(Trip $trip, User $sender): void
    {
        $tokens = $sender->devices
            ->filter(fn (UserDevice $device): bool => $device->active && filled($device->fcm_token))
            ->map(fn (UserDevice $device): string => (string) $device->fcm_token)
            ->unique()
            ->values()
            ->all();

        try {
            $this->pushGateway->sendToTokens(
                array_values($tokens),
                'Your delivery has been matched',
                'Your delivery has joined an ongoing pooled trip. A driver is on the way.',
                [
                    'trip_id' => (string) $trip->id,
                    'status' => 'matched',
                ],
            );
        } catch (Throwable $e) {
            Log::error('delivery_share.sender_notification_failed', ['trip_id' => $trip->id, 'error' => $e->getMessage()]);
        }
    }
}
