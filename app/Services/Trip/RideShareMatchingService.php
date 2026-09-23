<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\PricingRule;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Models\TripStop;
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

/**
 * Finds an ongoing ride-share trip a new request can merge into, and attaches the
 * requesting customer to it as an extra passenger.
 *
 * Matching runs three checks, cheapest first, against each nearby ongoing ride-share trip:
 *   1. direction   - bearing pre-filter, no routing call (cheap, rejects most bad candidates)
 *   2. capacity    - can the vehicle hold this passenger across the span they'd ride, given
 *                    everyone already scheduled on the remaining route (arithmetic only)
 *   3. detour      - how much extra driving time/distance would inserting this passenger's
 *                    pickup+dropoff add to the vehicle's remaining route (needs the routing
 *                    gateway - the expensive check, run last and only on survivors)
 *
 * Known v1 simplification: a new passenger's pickup and dropoff are only tried as an
 * *adjacent* pair inserted at each gap in the current remaining stop sequence (n+1
 * positions for n remaining stops). This keeps routing-API calls bounded, but it can't
 * find insertions where the new passenger's dropoff should interleave *between* two
 * existing stops rather than sit immediately after their pickup. Revisit with a real
 * insertion-heuristic/VRP solver if match quality proves too coarse in production.
 */
readonly class RideShareMatchingService
{
    public function __construct(
        private RoutingGateway $routing,
        private CheckoutService $checkout,
        private FcmGateway $pushGateway,
    ) {}

    public function match(
        User $customer,
        Point $pickup,
        ?string $pickupAddress,
        Point $dropoff,
        ?string $dropoffAddress,
        int $vehicleTypeId,
        int $seatsRequested,
        Zone $zone,
        PricingRule $pricingRule,
    ): ?TripPassenger {
        $best = null;

        foreach ($this->shortlistCandidates($pickup, $vehicleTypeId, $seatsRequested) as $trip) {
            $evaluation = $this->evaluateCandidate($trip, $pickup, $dropoff, $seatsRequested);

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

        return $this->attach($customer, $pickup, $pickupAddress, $dropoff, $dropoffAddress, $seatsRequested, $zone, $pricingRule, $best);
    }

    /**
     * @return Collection<int, Trip>
     */
    private function shortlistCandidates(Point $pickup, int $vehicleTypeId, int $seatsRequested): Collection
    {
        $radiusMeters = (float) Configuration::get('ride_share_candidate_search_radius_km', 5) * 1000;
        $maxCandidates = (int) Configuration::get('ride_share_max_candidates', 5);

        $candidates = Trip::query()
            ->where('type', 'ride_share')
            ->where('status', 'in_progress')
            ->where('vehicle_type_id', $vehicleTypeId)
            ->where('available_seats', '>=', $seatsRequested)
            ->whereHas('rider.riderProfile', function ($query) use ($pickup, $radiusMeters) {
                $query->whereNotNull('current_location')
                    ->where(ST::distanceSphere('current_location', $pickup), '<=', $radiusMeters);
            })
            ->with([
                'rider.riderProfile',
                'vehicleType',
                'stops' => fn ($query) => $query->whereNull('arrived_at')->orderBy('sequence'),
            ])
            ->get();

        return $candidates
            ->sortBy(fn (Trip $trip) => Geo::haversineKm($trip->rider->riderProfile->current_location, $pickup))
            ->take($maxCandidates)
            ->values();
    }

    /**
     * @return array{gap: int, route: RouteResult, detour_minutes: int, detour_km: float, remaining_stops: Collection<int, TripStop>, driver_location: Point}|null
     */
    private function evaluateCandidate(Trip $trip, Point $pickup, Point $dropoff, int $seatsRequested): ?array
    {
        $remainingStops = $trip->stops;

        if ($remainingStops->isEmpty()) {
            // Shouldn't happen for an in_progress ride-share trip (it should have
            // auto-completed once its last scheduled stop was reached), but guard anyway.
            return null;
        }

        $driverLocation = $trip->rider->riderProfile->current_location;

        if (! $this->passesDirectionCheck($driverLocation, $remainingStops->last()->location, $pickup)) {
            return null;
        }

        $capacity = (int) ($trip->vehicleType->capacity ?? 0);
        $occupiedNow = $capacity - (int) $trip->available_seats;
        $occupancyAtGap = $this->occupancyPrefix($remainingStops, $occupiedNow);

        $baseline = $this->routing->computeRoute([
            $driverLocation,
            ...array_values($remainingStops->pluck('location')->all()),
        ]);

        $maxDetourMinutes = (float) Configuration::get('ride_share_max_detour_minutes', 7);
        $maxDetourKm = (float) Configuration::get('ride_share_max_detour_km', 3);

        $best = null;

        for ($gap = 0; $gap <= $remainingStops->count(); $gap++) {
            if ($capacity < $occupancyAtGap[$gap] + $seatsRequested) {
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
        $maxDeviation = (float) Configuration::get('ride_share_max_bearing_deviation_degrees', 100);

        $overallBearing = Geo::bearingDegrees($driverLocation, $finalRemainingStop);
        $requestBearing = Geo::bearingDegrees($driverLocation, $pickup);

        return Geo::bearingDifference($overallBearing, $requestBearing) <= $maxDeviation;
    }

    /**
     * Running seat occupancy at each gap boundary (index 0 = before the first remaining
     * stop, index n = after the last), starting from the vehicle's current occupancy.
     *
     * @param  Collection<int, TripStop>  $remainingStops
     * @return list<int>
     */
    private function occupancyPrefix(Collection $remainingStops, int $occupiedNow): array
    {
        $prefix = [$occupiedNow];

        foreach ($remainingStops as $stop) {
            $prefix[] = end($prefix) + $stop->seats_delta;
        }

        return $prefix;
    }

    /**
     * @param  array{gap: int, route: RouteResult, detour_minutes: float, detour_km: float, remaining_stops: Collection<int, TripStop>, driver_location: Point, trip: Trip}  $winner
     */
    private function attach(
        User $customer,
        Point $pickup,
        ?string $pickupAddress,
        Point $dropoff,
        ?string $dropoffAddress,
        int $seatsRequested,
        Zone $zone,
        PricingRule $pricingRule,
        array $winner,
    ): ?TripPassenger {
        /** @var Trip $trip */
        $trip = $winner['trip'];

        // Atomic claim, same pattern as the solo-ride driver-accept claim: if another
        // request already consumed the seats since we evaluated this candidate, this
        // affects 0 rows and we report "no match" rather than retrying a stale plan
        // against a second-best candidate.
        $claimed = Trip::query()
            ->where('id', $trip->id)
            ->where('available_seats', '>=', $seatsRequested)
            ->decrement('available_seats', $seatsRequested, [
                'passenger_count' => DB::raw('passenger_count + 1'),
                'route_polyline' => $winner['route']->polyline,
                'route_distance_km' => $winner['route']->distanceKm,
                'route_duration_minutes' => $winner['route']->durationMinutes,
            ]);

        if ($claimed === 0) {
            return null;
        }

        $passengerLeg = $winner['route']->legs[$winner['gap'] + 1] ?? ['distance_km' => 0.0, 'duration_minutes' => 0];

        $fare = $this->checkout->calculateRideShareFare(
            $pricingRule,
            $zone,
            (int) $trip->vehicle_type_id,
            (float) $passengerLeg['distance_km'],
            (int) $passengerLeg['duration_minutes'],
            now(),
        );

        return DB::transaction(function () use ($trip, $customer, $pickup, $pickupAddress, $dropoff, $dropoffAddress, $seatsRequested, $zone, $passengerLeg, $fare, $winner) {
            $passenger = TripPassenger::query()->create([
                'trip_id' => $trip->id,
                'customer_id' => $customer->id,
                'seats_requested' => $seatsRequested,
                'status' => 'matched',
                'distance_km' => $passengerLeg['distance_km'],
                'duration_minutes' => $passengerLeg['duration_minutes'],
                'requested_at' => now(),
                'matched_at' => now(),
            ]);

            TripFareBreakdown::query()->create([
                'trip_id' => $trip->id,
                'passenger_id' => $passenger->id,
                'customer_id' => $customer->id,
                'base_fare' => $fare['base_fare'],
                'distance_fare' => $fare['distance_fare'],
                'time_fare' => $fare['time_fare'],
                'surge_multiplier' => $fare['surge_multiplier'],
                'surge_amount' => $fare['surge_amount'],
                'discount_percentage' => $fare['discount_percentage'],
                'estimated_fare' => $fare['fare'],
                'estimated_fare_before_rounding' => $fare['fare'],
                'currency_code' => $zone->currency_code,
                'payment_status' => 'pending',
            ]);

            $this->insertStopsAtGap($trip, $passenger, $winner['gap'], $winner['remaining_stops'], $pickup, $pickupAddress, $dropoff, $dropoffAddress, $seatsRequested);

            $this->notifyDriverOfNewPassenger($trip, $passenger);
            $this->notifyCustomerOfMatch($trip, $customer);

            return $passenger;
        });
    }

    /**
     * @param  Collection<int, TripStop>  $remainingStops
     */
    private function insertStopsAtGap(
        Trip $trip,
        TripPassenger $passenger,
        int $gap,
        Collection $remainingStops,
        Point $pickup,
        ?string $pickupAddress,
        Point $dropoff,
        ?string $dropoffAddress,
        int $seatsRequested,
    ): void {
        $arrivedCount = TripStop::query()->where('trip_id', $trip->id)->whereNotNull('arrived_at')->count();

        $before = $remainingStops->slice(0, $gap)->values();
        $after = $remainingStops->slice($gap)->values();

        // Two-phase renumber: bump every remaining stop out of the way first, so
        // reassigning final sequence numbers never collides with the unique(trip_id,
        // sequence) constraint while stops are shifting past each other.
        TripStop::query()
            ->where('trip_id', $trip->id)
            ->whereIn('id', $remainingStops->pluck('id'))
            ->update(['sequence' => DB::raw('sequence + 1000')]);

        $sequence = $arrivedCount + 1;

        foreach ($before as $stop) {
            $stop->update(['sequence' => $sequence++]);
        }

        TripStop::query()->create([
            'trip_id' => $trip->id,
            'trip_passenger_id' => $passenger->id,
            'stop_type' => 'pickup',
            'seats_delta' => $seatsRequested,
            'sequence' => $sequence++,
            'location' => $pickup,
            'address' => $pickupAddress,
        ]);

        TripStop::query()->create([
            'trip_id' => $trip->id,
            'trip_passenger_id' => $passenger->id,
            'stop_type' => 'dropoff',
            'seats_delta' => -$seatsRequested,
            'sequence' => $sequence++,
            'location' => $dropoff,
            'address' => $dropoffAddress,
        ]);

        foreach ($after as $stop) {
            $stop->update(['sequence' => $sequence++]);
        }
    }

    private function notifyDriverOfNewPassenger(Trip $trip, TripPassenger $passenger): void
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
                'New passenger added to your route',
                'A new passenger has joined your ride-share trip. Your route has been updated.',
                [
                    'trip_id' => (string) $trip->id,
                    'trip_passenger_id' => (string) $passenger->id,
                ],
            );
        } catch (\Throwable $e) {
            Log::error('ride_share.driver_notification_failed', ['trip_id' => $trip->id, 'error' => $e->getMessage()]);
        }
    }

    private function notifyCustomerOfMatch(Trip $trip, User $customer): void
    {
        $tokens = $customer->devices
            ->filter(fn (UserDevice $device): bool => $device->active && filled($device->fcm_token))
            ->map(fn (UserDevice $device): string => (string) $device->fcm_token)
            ->unique()
            ->values()
            ->all();

        try {
            $this->pushGateway->sendToTokens(
                array_values($tokens),
                'You have been matched',
                'You have joined an ongoing ride-share trip. Your driver is on the way.',
                [
                    'trip_id' => (string) $trip->id,
                    'status' => 'matched',
                ],
            );
        } catch (\Throwable $e) {
            Log::error('ride_share.customer_notification_failed', ['trip_id' => $trip->id, 'error' => $e->getMessage()]);
        }
    }
}
