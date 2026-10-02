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
use Illuminate\Validation\ValidationException;

/**
 * Finds an ongoing ride-share trip a new request can merge into and offers the requesting
 * customer to its driver, who must accept before the passenger joins the route.
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

    /**
     * Offers the request to the best-fitting ongoing ride-share trip (smallest detour) that
     * still has the seats, holding those seats while the driver decides. The passenger is
     * created -- or, on a re-offer, moved onto the trip -- as 'pending_approval', with their
     * pickup/dropoff stops unsequenced: they only join the route once the driver accepts.
     * Null when no ongoing trip fits.
     */
    public function offer(RideShareRequest $request, PricingRule $pricingRule, ?TripPassenger $passenger = null): ?TripPassenger
    {
        $excludeTripIds = $passenger->declined_trip_ids ?? [];
        $candidates = [];

        foreach ($this->shortlistCandidates($request->pickup, $request->vehicleTypeId, $request->seatsRequested, $excludeTripIds) as $trip) {
            $evaluation = $this->evaluateCandidate($trip, $request->pickup, $request->dropoff, $request->seatsRequested);

            if ($evaluation !== null) {
                $candidates[] = [...$evaluation, 'trip' => $trip];
            }
        }

        usort($candidates, fn (array $a, array $b): int => $a['detour_minutes'] <=> $b['detour_minutes']);

        foreach ($candidates as $candidate) {
            // Atomic claim, same pattern as the solo-ride driver-accept claim: if another
            // request already took the seats since we evaluated this candidate, move on to
            // the next one rather than over-filling the vehicle.
            $claimed = Trip::query()
                ->where('id', $candidate['trip']->id)
                ->where('status', 'in_progress')
                ->where('available_seats', '>=', $request->seatsRequested)
                ->decrement('available_seats', $request->seatsRequested);

            if ($claimed > 0) {
                return $this->createOffer($request, $pricingRule, $passenger, $candidate);
            }
        }

        return null;
    }

    /**
     * The driver accepts a passenger waiting on their approval: the passenger's stops are
     * slotted into the trip's current remaining route (re-planned now, since the route may
     * have changed while the request waited) and the passenger becomes 'matched', with
     * matched_at as the time of acceptance. Detour/direction limits aren't re-applied --
     * the driver has explicitly agreed to the pickup. Null when the trip can no longer
     * take the passenger (no longer in progress, or no capacity at any insertion point);
     * the caller then releases and re-offers the request.
     */
    public function accept(TripPassenger $passenger): ?TripPassenger
    {
        $accepted = DB::transaction(function () use ($passenger): ?TripPassenger {
            $passenger = TripPassenger::query()->lockForUpdate()->with(['stops', 'fareBreakdown'])->findOrFail($passenger->id);

            if ($passenger->status !== 'pending_approval' || $this->isReleased($passenger)) {
                throw ValidationException::withMessages([
                    'status' => 'This passenger is no longer waiting for your approval.',
                ]);
            }

            if ($passenger->request_expires_at?->isPast()) {
                throw ValidationException::withMessages([
                    'status' => 'This request has expired.',
                ]);
            }

            $trip = Trip::query()
                ->lockForUpdate()
                ->with([
                    'zone',
                    'rider.riderProfile',
                    'vehicleType',
                    'stops' => fn ($query) => $query->whereNull('arrived_at'),
                ])
                ->findOrFail($passenger->trip_id);

            $pickupStop = $passenger->stops->firstWhere('stop_type', 'pickup');
            $dropoffStop = $passenger->stops->firstWhere('stop_type', 'dropoff');

            if ($trip->status !== 'in_progress' || $trip->rider?->riderProfile?->current_location === null || $pickupStop === null || $dropoffStop === null) {
                return null;
            }

            $evaluation = $this->evaluateCandidate(
                $trip,
                $pickupStop->location,
                $dropoffStop->location,
                $passenger->seats_requested,
                heldSeats: $passenger->seats_requested,
                enforceLimits: false,
            );

            if ($evaluation === null) {
                return null;
            }

            $trip->increment('passenger_count', 1, [
                'route_polyline' => $evaluation['route']->polyline,
                'route_distance_km' => $evaluation['route']->distanceKm,
                'route_duration_minutes' => $evaluation['route']->durationMinutes,
            ]);

            $passengerLeg = $evaluation['route']->legs[$evaluation['gap'] + 1] ?? ['distance_km' => 0.0, 'duration_minutes' => 0];
            $pricingRule = $this->checkout->findPricingRule((int) $trip->zone_id, (int) $trip->vehicle_type_id);

            // Re-priced on the passenger's leg of the re-planned route; if the pricing rule
            // has since been removed, the estimate recorded at offer time stands.
            if ($pricingRule !== null && $trip->zone !== null) {
                $fare = $this->checkout->calculateRideShareFare(
                    $pricingRule,
                    $trip->zone,
                    (int) $trip->vehicle_type_id,
                    (float) $passengerLeg['distance_km'],
                    (int) $passengerLeg['duration_minutes'],
                    now(),
                );

                $passenger->fareBreakdown?->update($this->estimateFields($fare));
            }

            $passenger->update([
                'status' => 'matched',
                'matched_at' => now(),
                'distance_km' => $passengerLeg['distance_km'],
                'duration_minutes' => $passengerLeg['duration_minutes'],
            ]);

            $this->insertStopsAtGap($trip, $pickupStop, $dropoffStop, $evaluation['gap'], $evaluation['remaining_stops']);

            return $passenger;
        });

        if ($accepted !== null) {
            $this->notifyCustomerOfMatch($accepted->trip, $accepted->customer);
        }

        return $accepted;
    }

    /**
     * Ends the passenger's current offer (driver declined, it expired, or the customer
     * cancelled): returns the held seats to the trip and adds it to declined_trip_ids so a
     * re-offer never goes back to it. False when the offer was already released -- e.g.
     * the driver declined just as it expired -- so the caller doesn't re-offer it twice.
     */
    public function release(TripPassenger $passenger): bool
    {
        return DB::transaction(function () use ($passenger): bool {
            $passenger = TripPassenger::query()->lockForUpdate()->findOrFail($passenger->id);

            if ($passenger->status !== 'pending_approval' || $this->isReleased($passenger)) {
                return false;
            }

            $passenger->update([
                'declined_trip_ids' => [...($passenger->declined_trip_ids ?? []), (int) $passenger->trip_id],
                'request_expires_at' => null,
            ]);

            Trip::query()->where('id', $passenger->trip_id)->increment('available_seats', $passenger->seats_requested);

            return true;
        });
    }

    /**
     * A pending passenger whose current trip is already in declined_trip_ids has been
     * released from it and is waiting to be re-offered.
     */
    private function isReleased(TripPassenger $passenger): bool
    {
        return in_array((int) $passenger->trip_id, $passenger->declined_trip_ids ?? [], true);
    }

    /**
     * @param  array{gap: int, route: RouteResult, detour_minutes: int, detour_km: float, remaining_stops: Collection<int, TripStop>, driver_location: Point, trip: Trip}  $candidate
     */
    private function createOffer(RideShareRequest $request, PricingRule $pricingRule, ?TripPassenger $passenger, array $candidate): TripPassenger
    {
        $trip = $candidate['trip'];
        $passengerLeg = $candidate['route']->legs[$candidate['gap'] + 1] ?? ['distance_km' => 0.0, 'duration_minutes' => 0];

        $fare = $this->checkout->calculateRideShareFare(
            $pricingRule,
            $request->zone,
            $request->vehicleTypeId,
            (float) $passengerLeg['distance_km'],
            (int) $passengerLeg['duration_minutes'],
            now(),
        );

        $passenger = DB::transaction(function () use ($request, $passenger, $trip, $passengerLeg, $fare, $candidate): TripPassenger {
            $passengerFields = [
                'trip_id' => $trip->id,
                'status' => 'pending_approval',
                'distance_km' => $passengerLeg['distance_km'],
                'duration_minutes' => $passengerLeg['duration_minutes'],
                'detour_minutes' => max(0, (int) round($candidate['detour_minutes'])),
                'detour_km' => max(0.0, round($candidate['detour_km'], 2)),
                'request_expires_at' => now()->addSeconds((int) Configuration::get('ride_share_join_request_timeout_seconds', 30)),
            ];

            if ($passenger === null) {
                $passenger = TripPassenger::query()->create([
                    ...$passengerFields,
                    'customer_id' => $request->customer->id,
                    'seats_requested' => $request->seatsRequested,
                    'requested_at' => now(),
                ]);

                // Recorded but not on the route (no sequence) until the driver accepts.
                TripStop::query()->create([
                    'trip_id' => $trip->id,
                    'trip_passenger_id' => $passenger->id,
                    'stop_type' => 'pickup',
                    'seats_delta' => $request->seatsRequested,
                    'location' => $request->pickup,
                    'address' => $request->pickupAddress,
                ]);

                TripStop::query()->create([
                    'trip_id' => $trip->id,
                    'trip_passenger_id' => $passenger->id,
                    'stop_type' => 'dropoff',
                    'seats_delta' => -$request->seatsRequested,
                    'location' => $request->dropoff,
                    'address' => $request->dropoffAddress,
                ]);
            } else {
                $passenger->update($passengerFields);
                TripStop::query()->where('trip_passenger_id', $passenger->id)->update(['trip_id' => $trip->id, 'sequence' => null]);
            }

            TripFareBreakdown::query()->updateOrCreate(
                ['passenger_id' => $passenger->id],
                [
                    'trip_id' => $trip->id,
                    'customer_id' => $request->customer->id,
                    ...$this->estimateFields($fare),
                    'currency_code' => $request->zone->currency_code,
                    'payment_method' => $request->paymentMethod,
                    'payment_status' => 'pending',
                ],
            );

            return $passenger;
        });

        $this->notifyDriverOfJoinRequest($trip, $passenger, $request, $this->checkout->roundFare($fare['fare']));

        return $passenger;
    }

    /**
     * @param  array{base_fare: float, distance_fare: float, time_fare: float, surge_multiplier: float, surge_amount: float, discount_percentage: float, fare: float}  $fare
     * @return array<string, float>
     */
    private function estimateFields(array $fare): array
    {
        return [
            'base_fare' => $fare['base_fare'],
            'distance_fare' => $fare['distance_fare'],
            'time_fare' => $fare['time_fare'],
            'surge_multiplier' => $fare['surge_multiplier'],
            'surge_amount' => $fare['surge_amount'],
            'discount_percentage' => $fare['discount_percentage'],
            'estimated_fare' => $this->checkout->roundFare($fare['fare']),
            'estimated_fare_before_rounding' => $fare['fare'],
        ];
    }

    /**
     * @param  list<int>  $excludeTripIds
     * @return Collection<int, Trip>
     */
    private function shortlistCandidates(Point $pickup, int $vehicleTypeId, int $seatsRequested, array $excludeTripIds): Collection
    {
        $radiusMeters = (float) Configuration::get('ride_share_candidate_search_radius_km', 5) * 1000;
        $maxCandidates = (int) Configuration::get('ride_share_max_candidates', 5);

        $candidates = Trip::query()
            ->where('type', 'ride_share')
            ->where('status', 'in_progress')
            ->where('vehicle_type_id', $vehicleTypeId)
            ->where('available_seats', '>=', $seatsRequested)
            ->when($excludeTripIds !== [], fn ($query) => $query->whereNotIn('id', $excludeTripIds))
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
     * $heldSeats: seats this same request already holds on the trip (taken off
     * available_seats when it was offered), so they aren't counted against it twice.
     * $enforceLimits: false once the driver has accepted -- only capacity still applies.
     *
     * @return array{gap: int, route: RouteResult, detour_minutes: int, detour_km: float, remaining_stops: Collection<int, TripStop>, driver_location: Point}|null
     */
    private function evaluateCandidate(Trip $trip, Point $pickup, Point $dropoff, int $seatsRequested, int $heldSeats = 0, bool $enforceLimits = true): ?array
    {
        $remainingStops = $trip->stops;

        if ($remainingStops->isEmpty()) {
            // Shouldn't happen for an in_progress ride-share trip (it should have
            // auto-completed once its last scheduled stop was reached), but guard anyway.
            return null;
        }

        $driverLocation = $trip->rider->riderProfile->current_location;

        if ($enforceLimits && ! $this->passesDirectionCheck($driverLocation, $remainingStops->last()->location, $pickup)) {
            return null;
        }

        $capacity = (int) ($trip->vehicleType->capacity ?? 0);
        $occupiedNow = $capacity - (int) $trip->available_seats - $heldSeats;
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

            if ($enforceLimits && ($detourMinutes > $maxDetourMinutes || $detourKm > $maxDetourKm)) {
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
     * Gives the accepted passenger's (until now unsequenced) pickup/dropoff stops their
     * place on the route, at the chosen gap, renumbering the remaining stops around them.
     *
     * @param  Collection<int, TripStop>  $remainingStops
     */
    private function insertStopsAtGap(Trip $trip, TripStop $pickupStop, TripStop $dropoffStop, int $gap, Collection $remainingStops): void
    {
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

        $pickupStop->update(['sequence' => $sequence++]);
        $dropoffStop->update(['sequence' => $sequence++]);

        foreach ($after as $stop) {
            $stop->update(['sequence' => $sequence++]);
        }
    }

    private function notifyDriverOfJoinRequest(Trip $trip, TripPassenger $passenger, RideShareRequest $request, float $estimatedFare): void
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
                'New ride-share request',
                'A passenger wants to join your ride-share trip. Accept or decline before the request expires.',
                [
                    'type' => 'ride_share_join_request',
                    'trip_id' => (string) $trip->id,
                    'trip_passenger_id' => (string) $passenger->id,
                    'seats_requested' => (string) $request->seatsRequested,
                    'pickup_address' => (string) $request->pickupAddress,
                    'dropoff_address' => (string) $request->dropoffAddress,
                    'detour_minutes' => (string) $passenger->detour_minutes,
                    'estimated_fare' => (string) $estimatedFare,
                    'currency_code' => $request->zone->currency_code,
                    'expires_at' => (string) $passenger->request_expires_at?->toIso8601String(),
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
                'Your driver accepted your ride-share request and is on the way.',
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
