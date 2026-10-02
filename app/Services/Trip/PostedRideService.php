<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Checkout\CheckoutService;
use App\Services\Push\FcmGateway;
use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Posted rides: the driver posts a fixed origin -> destination trip for a set date at their
 * own per-seat fare; customers browse open ones and request seats, which the driver
 * approves or declines. Requested seats are held from the moment of booking, so approving
 * can never overbook. Every booking boards at the trip's origin and alights at its
 * destination, so its stops and fare are copied straight from the trip.
 *
 * Once the driver starts the trip it runs like a ride-share trip -- pickUpPassenger/
 * dropOffPassenger/settlePassengerCash in RiderTripService, auto-completing on the last
 * dropoff.
 */
readonly class PostedRideService
{
    /**
     * Passengers holding seats on the trip -- everyone not cancelled or dropped off.
     */
    private const array SEAT_HOLDING_STATUSES = ['pending_approval', 'matched', 'arrived_pickup', 'picked_up', 'arrived_dropoff'];

    public function __construct(
        private FcmGateway $pushGateway,
        private CheckoutService $checkout,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function post(User $user, array $data): Trip
    {
        if ($user->status === 'requested_delete') {
            throw ValidationException::withMessages([
                'account' => 'You cannot post rides while your account deletion request is pending. Cancel the deletion request to continue.',
            ]);
        }

        $riderProfile = $user->riderProfile;

        if (! $riderProfile || $riderProfile->kyc_status !== 'approved') {
            throw ValidationException::withMessages([
                'kyc_status' => 'Your account must be KYC-approved before you can post rides.',
            ]);
        }

        $vehicle = $riderProfile->vehicle;

        if (! $vehicle || $vehicle->status !== 'approved') {
            throw ValidationException::withMessages([
                'vehicle' => 'You need an approved vehicle before you can post rides.',
            ]);
        }

        $capacity = (int) $vehicle->vehicleType->capacity;
        $seats = (int) ($data['available_seats'] ?? $capacity);

        if ($seats > $capacity) {
            throw ValidationException::withMessages([
                'available_seats' => "Your vehicle can take at most {$capacity} passenger(s).",
            ]);
        }

        $origin = Point::makeGeodetic((float) $data['origin_latitude'], (float) $data['origin_longitude']);
        $destination = Point::makeGeodetic((float) $data['destination_latitude'], (float) $data['destination_longitude']);

        // The pricing rule isn't used for the fare (the driver sets that) -- only for the
        // commission taken at each dropoff, so it has to exist up front.
        $zone = $this->checkout->resolveZone($origin);
        $this->checkout->resolvePricingRule($zone->id, (int) $vehicle->vehicle_type_id);

        $distanceKm = (float) $data['distance_km'];

        $trip = Trip::query()->create([
            'trip_number' => $this->generateTripNumber(),
            'rider_id' => $user->id,
            'vehicle_id' => $vehicle->id,
            'vehicle_type_id' => $vehicle->vehicle_type_id,
            'zone_id' => $zone->id,
            'type' => 'posted_ride',
            'status' => 'open',
            'available_seats' => $seats,
            'passenger_count' => 0,
            'seat_fare' => $data['seat_fare'],
            'origin_location' => $origin,
            'origin_address' => $data['origin_address'] ?? null,
            'destination_location' => $destination,
            'destination_address' => $data['destination_address'] ?? null,
            'route_distance_km' => $distanceKm,
            'route_duration_minutes' => $this->checkout->durationMinutes($distanceKm),
            'requested_at' => now(),
            'accepted_at' => now(),
            'departs_at' => CarbonImmutable::parse($data['departs_at']),
        ]);

        return $trip->load(['vehicleType', 'zone']);
    }

    /**
     * Open posted rides a customer can still book: not yet departed, with enough seats
     * free, optionally near a given origin/destination and on a given date. Never the
     * customer's own posted rides.
     *
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Trip>
     */
    public function browse(User $user, array $filters): LengthAwarePaginator
    {
        $radiusMeters = (float) Configuration::get('posted_ride_search_radius_km', 5) * 1000;

        $origin = isset($filters['origin_latitude'], $filters['origin_longitude'])
            ? Point::makeGeodetic((float) $filters['origin_latitude'], (float) $filters['origin_longitude'])
            : null;
        $destination = isset($filters['destination_latitude'], $filters['destination_longitude'])
            ? Point::makeGeodetic((float) $filters['destination_latitude'], (float) $filters['destination_longitude'])
            : null;

        return Trip::query()
            ->where('type', 'posted_ride')
            ->where('status', 'open')
            ->where('departs_at', '>', now())
            ->where('rider_id', '!=', $user->id)
            ->where('available_seats', '>=', (int) ($filters['seats'] ?? 1))
            ->when($filters['vehicle_type_id'] ?? null, fn ($query, $vehicleTypeId) => $query->where('vehicle_type_id', $vehicleTypeId))
            ->when($filters['date'] ?? null, fn ($query, $date) => $query->whereDate('departs_at', $date))
            ->when($origin, fn ($query, $origin) => $query->where(ST::distanceSphere('origin_location', $origin), '<=', $radiusMeters))
            ->when($destination, fn ($query, $destination) => $query->where(ST::distanceSphere('destination_location', $destination), '<=', $radiusMeters))
            ->with($this->browseEagerLoads())
            ->orderBy('departs_at')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function showOpen(User $user, int $tripId): Trip
    {
        return Trip::query()
            ->where('id', $tripId)
            ->where('type', 'posted_ride')
            ->where('status', 'open')
            ->where('rider_id', '!=', $user->id)
            ->with($this->browseEagerLoads())
            ->firstOrFail();
    }

    /**
     * Requests seats on an open posted ride. The seats are held straight away; the
     * passenger stays 'pending_approval' (stops off the route, no sequence) until the
     * driver approves.
     *
     * @param  array<string, mixed>  $data
     */
    public function book(User $user, int $tripId, array $data): TripPassenger
    {
        if ($user->status === 'requested_delete') {
            throw ValidationException::withMessages([
                'account' => 'You cannot request trips while your account deletion request is pending. Cancel the deletion request to continue.',
            ]);
        }

        $trip = $this->showOpen($user, $tripId);
        $seats = (int) ($data['seats_requested'] ?? 1);

        if (TripPassenger::query()->where('trip_id', $trip->id)->where('customer_id', $user->id)->whereIn('status', self::SEAT_HOLDING_STATUSES)->exists()) {
            throw ValidationException::withMessages([
                'trip' => 'You have already booked this ride.',
            ]);
        }

        $passenger = DB::transaction(function () use ($user, $trip, $seats, $data): TripPassenger {
            $claimed = Trip::query()
                ->where('id', $trip->id)
                ->where('status', 'open')
                ->where('departs_at', '>', now())
                ->where('available_seats', '>=', $seats)
                ->decrement('available_seats', $seats);

            if ($claimed === 0) {
                throw ValidationException::withMessages([
                    'seats_requested' => 'There are not enough seats left on this ride.',
                ]);
            }

            $passenger = TripPassenger::query()->create([
                'trip_id' => $trip->id,
                'customer_id' => $user->id,
                'seats_requested' => $seats,
                'status' => 'pending_approval',
                'distance_km' => $trip->route_distance_km,
                'duration_minutes' => $trip->route_duration_minutes,
                'requested_at' => now(),
            ]);

            foreach ([
                ['pickup', $seats, $trip->origin_location, $trip->origin_address],
                ['dropoff', -$seats, $trip->destination_location, $trip->destination_address],
            ] as [$stopType, $seatsDelta, $location, $address]) {
                TripStop::query()->create([
                    'trip_id' => $trip->id,
                    'trip_passenger_id' => $passenger->id,
                    'stop_type' => $stopType,
                    'seats_delta' => $seatsDelta,
                    'sequence' => null,
                    'location' => $location,
                    'address' => $address,
                ]);
            }

            $fare = round((float) $trip->seat_fare * $seats, 2);

            TripFareBreakdown::query()->create([
                'trip_id' => $trip->id,
                'passenger_id' => $passenger->id,
                'customer_id' => $user->id,
                'base_fare' => $fare,
                'distance_fare' => 0,
                'time_fare' => 0,
                'surge_multiplier' => 1,
                'surge_amount' => 0,
                'discount_amount' => 0,
                'estimated_fare' => $fare,
                'estimated_fare_before_rounding' => $fare,
                'currency_code' => $trip->zone->currency_code,
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
            ]);

            return $passenger;
        });

        $this->notifyDriverOfBookingRequest($trip, $passenger);

        return $passenger->load(['customer', 'fareBreakdown', 'stops', 'cancellationReason', 'trip.rider.riderProfile', 'trip.vehicleType']);
    }

    /**
     * The driver approves a booking request: the passenger becomes 'matched' and their
     * stops join the route. Only before the trip starts -- any request still pending then
     * is cancelled by declineOutstanding().
     */
    public function approve(TripPassenger $passenger): TripPassenger
    {
        $approved = DB::transaction(function () use ($passenger): TripPassenger {
            $passenger = TripPassenger::query()->lockForUpdate()->findOrFail($passenger->id);
            $trip = Trip::query()->lockForUpdate()->findOrFail($passenger->trip_id);

            if ($passenger->status !== 'pending_approval') {
                throw ValidationException::withMessages([
                    'status' => 'This passenger is no longer waiting for your approval.',
                ]);
            }

            if ($trip->status !== 'open') {
                throw ValidationException::withMessages([
                    'trip' => 'Bookings can only be approved before the ride starts.',
                ]);
            }

            $passenger->update(['status' => 'matched', 'matched_at' => now()]);
            $trip->increment('passenger_count');

            $this->resequenceRoute($trip);

            return $passenger;
        });

        $approved->load('customer.devices');
        $this->notify(
            $approved->customer,
            'Booking approved',
            'Your driver approved your seat booking.',
            ['trip_id' => (string) $approved->trip_id, 'trip_passenger_id' => (string) $approved->id, 'status' => 'matched'],
        );

        return $approved;
    }

    /**
     * The driver declines a booking request, returning its held seats.
     */
    public function decline(TripPassenger $passenger, User $driver): void
    {
        $declined = $this->cancelPending($passenger, $driver, null);

        if ($declined) {
            $passenger->load('customer.devices');
            $this->notify(
                $passenger->customer,
                'Booking declined',
                'The driver declined your seat booking.',
                ['trip_id' => (string) $passenger->trip_id, 'trip_passenger_id' => (string) $passenger->id, 'status' => 'cancelled'],
            );
        }
    }

    /**
     * The customer cancels their own booking. Its seats go back to the trip and its
     * unvisited stops come off the route; the trip itself carries on regardless.
     */
    public function cancelBooking(TripPassenger $passenger, User $user, ?int $cancellationReasonId): void
    {
        if ($passenger->status === 'pending_approval') {
            $this->cancelPending($passenger, $user, $cancellationReasonId);

            return;
        }

        DB::transaction(function () use ($passenger, $user, $cancellationReasonId): void {
            $passenger->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason_id' => $cancellationReasonId,
            ]);

            $passenger->stops()->whereNull('arrived_at')->update(['sequence' => null]);

            $trip = $passenger->trip;

            if (in_array($trip->status, ['open', 'in_progress'], true)) {
                $trip->increment('available_seats', $passenger->seats_requested);
                $trip->decrement('passenger_count');
            }
        });
    }

    /**
     * Cancels every request the driver never answered -- called when the trip starts,
     * since bookings can't be approved after that.
     */
    public function declineOutstanding(Trip $trip, User $driver): void
    {
        TripPassenger::query()
            ->where('trip_id', $trip->id)
            ->where('status', 'pending_approval')
            ->get()
            ->each(fn (TripPassenger $passenger) => $this->decline($passenger, $driver));
    }

    /**
     * The driver cancels the whole posted ride: every booking still active (or still
     * waiting on approval) is cancelled and its customer notified. Unlike an on-demand
     * trip it isn't re-dispatched -- the ride was the driver's own offer.
     */
    public function cancelTrip(Trip $trip, User $driver, ?int $cancellationReasonId): void
    {
        $affected = TripPassenger::query()
            ->where('trip_id', $trip->id)
            ->whereIn('status', self::SEAT_HOLDING_STATUSES)
            ->with('customer.devices')
            ->get();

        DB::transaction(function () use ($trip, $driver, $cancellationReasonId, $affected): void {
            $cancelUpdate = [
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $driver->id,
                'cancellation_reason_id' => $cancellationReasonId,
            ];

            $trip->update($cancelUpdate);

            TripPassenger::query()->whereKey($affected->modelKeys())->update($cancelUpdate);
        });

        foreach ($affected as $passenger) {
            $this->notify(
                $passenger->customer,
                'Ride cancelled',
                'The driver cancelled the ride you booked.',
                ['trip_id' => (string) $trip->id, 'trip_passenger_id' => (string) $passenger->id, 'status' => 'cancelled'],
            );
        }
    }

    /**
     * False when the request was no longer pending (e.g. the customer cancelled just as the
     * driver declined), so its seats aren't returned twice.
     */
    private function cancelPending(TripPassenger $passenger, User $by, ?int $cancellationReasonId): bool
    {
        return DB::transaction(function () use ($passenger, $by, $cancellationReasonId): bool {
            $locked = TripPassenger::query()->lockForUpdate()->findOrFail($passenger->id);

            if ($locked->status !== 'pending_approval') {
                return false;
            }

            $locked->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $by->id,
                'cancellation_reason_id' => $cancellationReasonId,
            ]);

            Trip::query()->where('id', $locked->trip_id)->increment('available_seats', $locked->seats_requested);

            return true;
        });
    }

    /**
     * Every approved passenger boards at the origin and alights at the destination, so the
     * route is simply all pickups (in approval order) followed by all dropoffs.
     */
    private function resequenceRoute(Trip $trip): void
    {
        $stops = TripStop::query()
            ->where('trip_id', $trip->id)
            ->whereHas('tripPassenger', fn ($query) => $query->whereIn('status', ['matched', 'arrived_pickup', 'picked_up', 'arrived_dropoff', 'dropped_off']))
            ->with('tripPassenger')
            ->get()
            ->sortBy([
                fn (TripStop $a, TripStop $b) => ($a->stop_type === 'pickup' ? 0 : 1) <=> ($b->stop_type === 'pickup' ? 0 : 1),
                fn (TripStop $a, TripStop $b) => [$a->tripPassenger->matched_at, $a->trip_passenger_id] <=> [$b->tripPassenger->matched_at, $b->trip_passenger_id],
            ])
            ->values();

        // Cleared first so renumbering never collides with the unique(trip_id, sequence)
        // constraint while stops shift past each other.
        TripStop::query()->whereKey($stops->modelKeys())->update(['sequence' => null]);

        foreach ($stops as $index => $stop) {
            TripStop::query()->whereKey($stop->id)->update(['sequence' => $index + 1]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function browseEagerLoads(): array
    {
        return ['rider.riderProfile', 'vehicle', 'vehicleType', 'zone'];
    }

    private function notifyDriverOfBookingRequest(Trip $trip, TripPassenger $passenger): void
    {
        $driver = $trip->rider;

        if ($driver === null) {
            return;
        }

        $this->notify($driver, 'New seat booking', 'A passenger wants to book seats on your posted ride. Approve or decline the request.', [
            'type' => 'posted_ride_booking_request',
            'trip_id' => (string) $trip->id,
            'trip_passenger_id' => (string) $passenger->id,
            'seats_requested' => (string) $passenger->seats_requested,
        ]);
    }

    /**
     * @param  array<non-empty-string, string>  $data
     */
    private function notify(User $user, string $title, string $body, array $data): void
    {
        $tokens = $user->devices
            ->filter(fn (UserDevice $device): bool => $device->active && filled($device->fcm_token))
            ->map(fn (UserDevice $device): string => (string) $device->fcm_token)
            ->unique()
            ->values()
            ->all();

        try {
            $this->pushGateway->sendToTokens(array_values($tokens), $title, $body, $data);
        } catch (Throwable $e) {
            Log::error('posted_ride.notification_failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
        }
    }

    private function generateTripNumber(): string
    {
        do {
            $tripNumber = 'TRP-'.now()->format('ymd').strtoupper(Str::random(6));
        } while (Trip::query()->where('trip_number', $tripNumber)->exists());

        return $tripNumber;
    }
}
