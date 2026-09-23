<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\DeliveryDetails;
use App\Models\DeliveryStop;
use App\Models\RiderProfile;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripLocation;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Wallet;
use App\Services\Checkout\CheckoutService;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Push\FcmGateway;
use App\Services\Wallet\TransactionService;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class RiderTripService
{
    private const array ACTIVE_STATUSES = ['accepted', 'arrived', 'in_progress'];

    private const array ACTIVE_PASSENGER_STATUSES = ['requested', 'matched', 'arrived_pickup', 'picked_up', 'arrived_dropoff'];

    public function __construct(
        private FcmGateway $pushGateway,
        private CheckoutService $checkout,
        private PaymentGateway $paymentGateway,
        private TransactionService $transactionService,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Trip>
     */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $query = Trip::query()
            ->where('rider_id', $user->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            // rider_earning only exists on trip_passengers' fare breakdown for now (delivery
            // isn't wired up yet).
            ->when($filters['min_fare'] ?? null, fn ($query, $minFare) => $query->whereHas(
                'passengers.fareBreakdown', fn ($query) => $query->where('rider_earning', '>=', $minFare)
            ))
            ->when($filters['max_fare'] ?? null, fn ($query, $maxFare) => $query->whereHas(
                'passengers.fareBreakdown', fn ($query) => $query->where('rider_earning', '<=', $maxFare)
            ))
            ->with(['vehicleType', 'deliveries.sender', 'passengers.customer', 'passengers.fareBreakdown'])
            ->orderByDesc('requested_at');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Trip>
     */
    public function nearby(User $user, array $filters): LengthAwarePaginator
    {
        $riderProfile = $this->riderProfile($user);

        if ($riderProfile->kyc_status !== 'approved') {
            throw ValidationException::withMessages([
                'kyc_status' => 'Your account must be KYC-approved before you can view nearby rides.',
            ]);
        }

        $vehicle = $riderProfile->vehicle;

        if (! $vehicle || $vehicle->status !== 'approved') {
            throw ValidationException::withMessages([
                'vehicle' => 'You need an approved vehicle before you can view nearby rides.',
            ]);
        }

        if (! $riderProfile->current_location) {
            throw ValidationException::withMessages([
                'current_location' => 'Please update your current location before viewing nearby rides.',
            ]);
        }

        $point = $riderProfile->current_location;
        $radiusMeters = (float) Configuration::get('search_radius_km', 5) * 1000;

        // trips no longer carry their own pickup_location -- a 'searching' trip always has
        // exactly one seed passenger/delivery at this point (further ride_share/delivery_share
        // joins attach directly via the matching services, not through this browse endpoint),
        // so its pickup stop is the trip's pickup point.
        $pickupStopLocation = DB::raw(<<<'SQL'
            COALESCE(
                (SELECT location FROM trip_stops WHERE trip_stops.trip_id = trips.id AND trip_stops.stop_type = 'pickup' ORDER BY trip_stops.sequence ASC LIMIT 1),
                (SELECT location FROM delivery_stops WHERE delivery_stops.trip_id = trips.id AND delivery_stops.stop_type = 'pickup' ORDER BY delivery_stops.sequence ASC LIMIT 1)
            )
            SQL);

        return Trip::query()
            ->where('status', 'searching')
            ->whereNull('rider_id')
            ->where('vehicle_type_id', $vehicle->vehicle_type_id)
            ->where(ST::distanceSphere($pickupStopLocation, $point), '<=', $radiusMeters)
            ->orderBy(ST::distanceSphere($pickupStopLocation, $point))
            ->with(['vehicleType', 'deliveries.sender', 'passengers.customer', 'passengers.fareBreakdown'])
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function show(User $user, int $tripId): Trip
    {
        return Trip::query()
            ->where('rider_id', $user->id)
            ->where('id', $tripId)
            ->with([
                'vehicleType', 'cancellationReason',
                'passengers.customer', 'passengers.stops', 'passengers.fareBreakdown',
                'deliveries.sender', 'deliveries.stops',
            ])
            ->firstOrFail();
    }

    public function accept(User $user, int $tripId): Trip
    {
        if ($user->status === 'requested_delete') {
            throw ValidationException::withMessages([
                'account' => 'You cannot accept rides while your account deletion request is pending. Cancel the deletion request to continue.',
            ]);
        }

        $riderProfile = $this->riderProfile($user);

        if ($riderProfile->kyc_status !== 'approved') {
            throw ValidationException::withMessages([
                'kyc_status' => 'Your account must be KYC-approved before you can accept rides.',
            ]);
        }

        $claimed = Trip::query()
            ->where('id', $tripId)
            ->whereIn('status', ['requested', 'searching'])
            ->whereNull('rider_id')
            ->update([
                'status' => 'accepted',
                'rider_id' => $user->id,
                'vehicle_id' => $riderProfile->vehicle?->id,
                'accepted_at' => now(),
            ]);

        if ($claimed === 0) {
            throw ValidationException::withMessages([
                'trip' => 'This ride is no longer available.',
            ]);
        }

        $riderProfile->update(['availability_status' => 'on_trip']);

        TripPassenger::query()
            ->where('trip_id', $tripId)
            ->where('status', 'requested')
            ->update(['status' => 'matched', 'matched_at' => now()]);

        DeliveryDetails::query()
            ->where('trip_id', $tripId)
            ->where('status', 'requested')
            ->update(['status' => 'matched', 'matched_at' => now()]);

        $trip = Trip::query()
            ->with(['vehicleType', 'deliveries.sender.devices', 'passengers.customer.devices', 'passengers.fareBreakdown'])
            ->findOrFail($tripId);

        try {
            $this->notifyCustomerOfAcceptance($trip, $user);
        } catch (Throwable $e) {
            Log::error('trip.acceptance_notification_failed', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $trip;
    }

    public function arrive(User $user, int $tripId): Trip
    {
        $trip = $this->ownRide($user, $tripId);

        if ($trip->status !== 'accepted') {
            throw ValidationException::withMessages([
                'status' => 'This ride cannot be marked as arrived from its current status.',
            ]);
        }

        $trip->update([
            'status' => 'arrived',
            'arrived_at' => now(),
        ]);

        if (in_array($trip->type, ['ride', 'ride_share'], true)) {
            $this->markFirstPassengerStop($trip, 'pickup', 'arrived_pickup');
        } elseif (in_array($trip->type, ['delivery', 'delivery_share'], true)) {
            $this->markFirstDeliveryStop($trip, 'pickup', 'arrived_pickup');
        }

        $trip = $trip->refresh()->load(['vehicleType', 'deliveries.sender.devices', 'passengers.customer.devices', 'passengers.fareBreakdown']);

        try {
            $this->notifyCustomerOfArrival($trip);
        } catch (Throwable $e) {
            Log::error('trip.arrival_notification_failed', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $trip;
    }

    public function start(User $user, int $tripId): Trip
    {
        $trip = $this->ownRide($user, $tripId);

        if (! in_array($trip->status, ['accepted', 'arrived'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This ride cannot be started from its current status.',
            ]);
        }

        $trip->update([
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        if (in_array($trip->type, ['ride', 'ride_share'], true)) {
            $this->markFirstPassengerStop($trip, 'pickup', 'picked_up');
        } elseif (in_array($trip->type, ['delivery', 'delivery_share'], true)) {
            $this->markFirstDeliveryStop($trip, 'pickup', 'picked_up');
        }

        return $trip->refresh()->load(['vehicleType', 'deliveries.sender', 'passengers.customer', 'passengers.fareBreakdown']);
    }

    /**
     * Ride/ride-share only: marks the trip's original (first) passenger's pickup/dropoff
     * stop as arrived and advances their own status, mirroring what arrive()/start() do at
     * the whole-trip level. Every passenger who joins afterwards progresses only through
     * pickUpPassenger()/dropOffPassenger() instead, since the trip-level status stays
     * 'in_progress' for the rest of the shared journey.
     */
    private function markFirstPassengerStop(Trip $trip, string $stopType, string $passengerStatus): void
    {
        $passenger = TripPassenger::query()
            ->where('trip_id', $trip->id)
            ->orderBy('requested_at')
            ->first();

        if ($passenger === null) {
            return;
        }

        TripStop::query()
            ->where('trip_id', $trip->id)
            ->where('trip_passenger_id', $passenger->id)
            ->where('stop_type', $stopType)
            ->update(['arrived_at' => now()]);

        $passenger->update([
            'status' => $passengerStatus,
            'picked_up_at' => $passengerStatus === 'picked_up' ? now() : $passenger->picked_up_at,
        ]);
    }

    /**
     * Delivery/delivery-share equivalent of markFirstPassengerStop().
     */
    private function markFirstDeliveryStop(Trip $trip, string $stopType, string $deliveryStatus): void
    {
        $delivery = DeliveryDetails::query()
            ->where('trip_id', $trip->id)
            ->orderBy('requested_at')
            ->first();

        if ($delivery === null) {
            return;
        }

        DeliveryStop::query()
            ->where('trip_id', $trip->id)
            ->where('delivery_details_id', $delivery->id)
            ->where('stop_type', $stopType)
            ->update(['arrived_at' => now()]);

        $delivery->update([
            'status' => $deliveryStatus,
            'picked_up_at' => $deliveryStatus === 'picked_up' ? now() : $delivery->picked_up_at,
        ]);
    }

    public function cancel(User $user, int $tripId, ?int $cancellationReasonId): Trip
    {
        $riderProfile = $this->riderProfile($user);
        $trip = $this->ownRide($user, $tripId);

        if (! in_array($trip->status, self::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This ride can no longer be cancelled.',
            ]);
        }

        DB::transaction(function () use ($trip, $user, $cancellationReasonId): void {
            $trip->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason_id' => $cancellationReasonId,
            ]);

            // Cancelling the whole vehicle trip cancels every passenger/delivery still on
            // it too (no cancellation fee, same as an individual cancel) -- one already
            // dropped off keeps its completed status untouched.
            $cancelUpdate = [
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancelled_by' => $user->id,
                'cancellation_reason_id' => $cancellationReasonId,
            ];

            if (in_array($trip->type, ['ride', 'ride_share'], true)) {
                TripPassenger::query()
                    ->where('trip_id', $trip->id)
                    ->whereIn('status', self::ACTIVE_PASSENGER_STATUSES)
                    ->update($cancelUpdate);
            } elseif (in_array($trip->type, ['delivery', 'delivery_share'], true)) {
                DeliveryDetails::query()
                    ->where('trip_id', $trip->id)
                    ->whereIn('status', self::ACTIVE_PASSENGER_STATUSES)
                    ->update($cancelUpdate);
            }
        });

        // trips no longer carry their own pickup_location -- fall back to the earliest
        // pickup stop recorded for this trip, which is what trip->pickup_location always
        // pointed at anyway (the first passenger/delivery's pickup point).
        $pickupStop = in_array($trip->type, ['ride', 'ride_share'], true)
            ? TripStop::query()->where('trip_id', $trip->id)->where('stop_type', 'pickup')->orderBy('sequence')->first()
            : DeliveryStop::query()->where('trip_id', $trip->id)->where('stop_type', 'pickup')->orderBy('sequence')->first();
        $pickup = $pickupStop?->location;
        $vehicleTypeId = (int) $trip->vehicle_type_id;

        Log::info('trip.rider_cancelled', [
            'trip_id' => $trip->id,
            'rider_id' => $user->id,
            'cancellation_reason_id' => $cancellationReasonId,
        ]);

        $trip->update([
            'status' => 'searching',
            'rider_id' => null,
            'vehicle_id' => null,
            'accepted_at' => null,
            'arrived_at' => null,
            'started_at' => null,
        ]);

        $riderProfile->update(['availability_status' => 'online']);

        $trip = $trip->refresh()->load(['vehicleType', 'deliveries.sender.devices', 'passengers.customer.devices', 'passengers.fareBreakdown']);

        if ($pickup !== null) {
            try {
                $this->dispatchToNearbyRiders($trip, $pickup, $vehicleTypeId, $user->id, $pickupStop->address);
            } catch (Throwable $e) {
                Log::error('trip.rider_cancellation_dispatch_failed', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->notifyCustomerOfRiderCancellation($trip);
        } catch (Throwable $e) {
            Log::error('trip.rider_cancellation_notification_failed', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $trip;
    }

    /**
     * Ride-share only: pick up one specific passenger, marking their pickup stop arrived
     * and advancing their own status. Does not touch the vehicle trip's own status, which
     * stays 'in_progress' for the whole shared journey.
     */
    public function pickUpPassenger(User $user, int $tripId, int $tripPassengerId): TripPassenger
    {
        $trip = $this->ownRide($user, $tripId);

        if ($trip->type !== 'ride_share') {
            throw ValidationException::withMessages([
                'type' => 'This action is only available on ride-share trips.',
            ]);
        }

        $passenger = TripPassenger::query()
            ->where('trip_id', $tripId)
            ->where('id', $tripPassengerId)
            ->firstOrFail();

        if (! in_array($passenger->status, ['matched', 'arrived_pickup'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This passenger cannot be picked up from their current status.',
            ]);
        }

        DB::transaction(function () use ($trip, $passenger): void {
            TripStop::query()
                ->where('trip_id', $trip->id)
                ->where('trip_passenger_id', $passenger->id)
                ->where('stop_type', 'pickup')
                ->update(['arrived_at' => now()]);

            $passenger->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        });

        return $passenger->fresh()->load(['customer', 'fareBreakdown', 'stops']);
    }

    /**
     * Delivery-share only: pick up one specific delivery -- mirrors pickUpPassenger().
     */
    public function pickUpDelivery(User $user, int $tripId, int $deliveryDetailsId): DeliveryDetails
    {
        $trip = $this->ownRide($user, $tripId);

        if ($trip->type !== 'delivery_share') {
            throw ValidationException::withMessages([
                'type' => 'This action is only available on pooled-delivery trips.',
            ]);
        }

        $delivery = DeliveryDetails::query()
            ->where('trip_id', $tripId)
            ->where('id', $deliveryDetailsId)
            ->firstOrFail();

        if (! in_array($delivery->status, ['matched', 'arrived_pickup'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This delivery cannot be picked up from its current status.',
            ]);
        }

        DB::transaction(function () use ($trip, $delivery): void {
            DeliveryStop::query()
                ->where('trip_id', $trip->id)
                ->where('delivery_details_id', $delivery->id)
                ->where('stop_type', 'pickup')
                ->update(['arrived_at' => now()]);

            $delivery->update(['status' => 'picked_up', 'picked_up_at' => now()]);
        });

        return $delivery->fresh()->load(['sender', 'stops']);
    }

    /**
     * Ride-share only: drop off one specific passenger, settling their own fare
     * independently of every other passenger on the trip. Auto-completes the vehicle trip
     * once this was the last passenger/delivery still active on it.
     *
     * A solo ride's one-and-only passenger settles the same way, but through end() instead
     * -- see settleDropOff(), shared by both (and by their delivery equivalents).
     */
    public function dropOffPassenger(User $user, int $tripId, int $tripPassengerId): TripPassenger
    {
        $riderProfile = $this->riderProfile($user);
        $trip = $this->ownRide($user, $tripId)->load('zone');

        if ($trip->type !== 'ride_share') {
            throw ValidationException::withMessages([
                'type' => 'This action is only available on ride-share trips.',
            ]);
        }

        $passenger = TripPassenger::query()
            ->where('trip_id', $tripId)
            ->where('id', $tripPassengerId)
            ->with('trip')
            ->firstOrFail();

        if (! in_array($passenger->status, ['picked_up', 'arrived_dropoff'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This passenger cannot be dropped off from their current status.',
            ]);
        }

        /** @var TripPassenger */
        return DB::transaction(fn () => $this->settleDropOff($passenger, $trip, $riderProfile));
    }

    /**
     * Delivery-share only: drop off one specific delivery -- mirrors dropOffPassenger().
     */
    public function dropOffDelivery(User $user, int $tripId, int $deliveryDetailsId): DeliveryDetails
    {
        $riderProfile = $this->riderProfile($user);
        $trip = $this->ownRide($user, $tripId)->load('zone');

        if ($trip->type !== 'delivery_share') {
            throw ValidationException::withMessages([
                'type' => 'This action is only available on pooled-delivery trips.',
            ]);
        }

        $delivery = DeliveryDetails::query()
            ->where('trip_id', $tripId)
            ->where('id', $deliveryDetailsId)
            ->with('trip')
            ->firstOrFail();

        if (! in_array($delivery->status, ['picked_up', 'arrived_dropoff'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This delivery cannot be dropped off from their current status.',
            ]);
        }

        /** @var DeliveryDetails */
        return DB::transaction(fn () => $this->settleDropOff($delivery, $trip, $riderProfile));
    }

    /**
     * Marks a passenger's or delivery's dropoff stop reached, settles its fare
     * independently (own payment method, own commission), and auto-completes the vehicle
     * trip once nothing is still active on it -- shared by dropOffPassenger()/
     * dropOffDelivery() (an explicit one is chosen) and end() (a solo trip's one-and-only
     * passenger/delivery is resolved automatically).
     */
    private function settleDropOff(TripPassenger|DeliveryDetails $settleable, Trip $trip, RiderProfile $riderProfile): TripPassenger|DeliveryDetails
    {
        if ($settleable instanceof DeliveryDetails) {
            DeliveryStop::query()
                ->where('trip_id', $trip->id)
                ->where('delivery_details_id', $settleable->id)
                ->where('stop_type', 'dropoff')
                ->update(['arrived_at' => now()]);
        } else {
            TripStop::query()
                ->where('trip_id', $trip->id)
                ->where('trip_passenger_id', $settleable->id)
                ->where('stop_type', 'dropoff')
                ->update(['arrived_at' => now()]);
        }

        $fareBreakdown = $this->checkout->recalculatePassengerFare($settleable);
        $finalFare = $fareBreakdown['fare'];
        $pricingRule = $this->checkout->resolvePricingRule((int) $trip->zone_id, (int) $trip->vehicle_type_id);
        $commissionAmount = round($finalFare * ((float) $pricingRule->commission_rate / 100), 2);
        $riderEarning = round($finalFare - $commissionAmount, 2);

        // The record holding this settlement's fare/payment fields -- a ride passenger's own
        // TripFareBreakdown row, or the DeliveryDetails row itself (delivery hasn't moved
        // its fare/payment data there yet).
        $paymentRecord = $settleable instanceof TripPassenger ? $settleable->fareBreakdown : $settleable;

        $settleable->update([
            'status' => 'dropped_off',
            'dropped_off_at' => now(),
            ...($settleable instanceof DeliveryDetails ? ['final_fare' => $finalFare] : []),
        ]);

        if ($paymentRecord instanceof TripFareBreakdown) {
            $paymentRecord->update([
                'distance_fare' => $fareBreakdown['distance_fare'],
                'time_fare' => $fareBreakdown['time_fare'],
                'surge_multiplier' => $fareBreakdown['surge_multiplier'],
                'surge_amount' => $fareBreakdown['surge_amount'],
                'final_fare' => $finalFare,
                'final_fare_before_rounding' => $fareBreakdown['fare_before_rounding'],
                'commission_rate' => $pricingRule->commission_rate,
                'commission_amount' => $commissionAmount,
                'rider_earning' => $riderEarning,
                'total' => $finalFare,
            ]);
        }

        if ($paymentRecord?->payment_method === 'wallet') {
            $this->settleWalletPayment($settleable, $paymentRecord, $riderProfile, $finalFare, $riderEarning, $commissionAmount);
        } elseif ($paymentRecord?->payment_method === 'cash') {
            $this->settleCashPayment($settleable, $paymentRecord, $riderProfile, $finalFare, $riderEarning, $commissionAmount);
        } elseif ($paymentRecord?->payment_method === 'mobile_money') {
            $this->settleMobileMoneyPayment($settleable, $paymentRecord, $riderProfile, $finalFare, $riderEarning, $commissionAmount);
        }

        $riderProfile->increment('total_trips');
        $riderProfile->increment('total_earnings', $riderEarning);

        $this->maybeCompleteTrip($trip, $riderProfile);

        return $settleable instanceof DeliveryDetails
            ? $settleable->fresh()->load(['sender', 'stops'])
            : $settleable->fresh()->load(['customer', 'fareBreakdown', 'stops']);
    }

    /**
     * Completes the vehicle trip once no passenger/delivery is still active on it
     * (requested through arrived_dropoff) -- the "auto-close when the last scheduled
     * dropoff happens" rule. A solo trip always has exactly one passenger/delivery, so this
     * fires immediately on its dropoff.
     */
    private function maybeCompleteTrip(Trip $trip, RiderProfile $riderProfile): void
    {
        $hasActive = in_array($trip->type, ['ride', 'ride_share'], true)
            ? TripPassenger::query()->where('trip_id', $trip->id)->whereIn('status', self::ACTIVE_PASSENGER_STATUSES)->exists()
            : DeliveryDetails::query()->where('trip_id', $trip->id)->whereIn('status', self::ACTIVE_PASSENGER_STATUSES)->exists();

        if ($hasActive) {
            return;
        }

        $trip->update(['status' => 'completed', 'completed_at' => now()]);
        $riderProfile->update(['availability_status' => 'online']);
    }

    public function end(User $user, int $tripId, ?UploadedFile $proofOfDeliveryPhoto = null): Trip
    {
        $riderProfile = $this->riderProfile($user);
        $trip = $this->ownRide($user, $tripId)->load('zone');

        if (in_array($trip->type, ['ride_share', 'delivery_share'], true)) {
            throw ValidationException::withMessages([
                'type' => 'Shared trips are ended by dropping off each passenger/delivery individually, not as a whole trip.',
            ]);
        }

        if (! in_array($trip->status, self::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This ride cannot be ended from its current status.',
            ]);
        }

        if ($trip->type === 'ride') {
            $passenger = TripPassenger::query()->where('trip_id', $trip->id)->firstOrFail();

            if (! in_array($passenger->status, ['picked_up', 'arrived_dropoff'], true)) {
                throw ValidationException::withMessages([
                    'status' => 'This ride cannot be ended from its current status.',
                ]);
            }

            return DB::transaction(function () use ($trip, $passenger, $riderProfile): Trip {
                $this->settleDropOff($passenger, $trip, $riderProfile);

                return $trip->fresh()->load(['vehicleType', 'passengers.stops', 'passengers.customer', 'passengers.fareBreakdown']);
            });
        }

        // Delivery.
        $delivery = DeliveryDetails::query()->where('trip_id', $trip->id)->firstOrFail();

        if (! in_array($delivery->status, ['picked_up', 'arrived_dropoff'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This delivery cannot be ended from its current status.',
            ]);
        }

        if (! $proofOfDeliveryPhoto) {
            throw ValidationException::withMessages([
                'proof_of_delivery_photo' => 'A photo of the delivered package is required to complete this delivery.',
            ]);
        }

        $proofOfDeliveryPhotoUrl = $this->storeProofOfDeliveryPhoto($trip, $proofOfDeliveryPhoto);

        return DB::transaction(function () use ($trip, $delivery, $riderProfile, $proofOfDeliveryPhotoUrl): Trip {
            $this->settleDropOff($delivery, $trip, $riderProfile);
            $delivery->update(['proof_of_delivery_photo' => $proofOfDeliveryPhotoUrl]);

            return $trip->fresh()->load(['vehicleType', 'deliveries.stops', 'deliveries.sender']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function logLocation(User $user, int $tripId, array $data): TripLocation
    {
        $riderProfile = $this->riderProfile($user);
        $trip = $this->ownRide($user, $tripId);

        if (! in_array($trip->status, self::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'Location can only be logged for an active ride.',
            ]);
        }

        return TripLocation::query()->create([
            'trip_id' => $trip->id,
            'rider_profile_id' => $riderProfile->id,
            'location' => Point::makeGeodetic((float) $data['latitude'], (float) $data['longitude']),
            'heading' => $data['heading'] ?? null,
            'speed' => $data['speed'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);
    }

    private function storeProofOfDeliveryPhoto(Trip $trip, UploadedFile $file): string
    {
        $path = $file->store("deliveries/{$trip->id}", 'public');

        if ($path === false) {
            throw ValidationException::withMessages([
                'proof_of_delivery_photo' => 'Failed to upload proof of delivery photo.',
            ]);
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * @return list<string>
     */
    private function primaryCustomerDeviceTokens(Trip $trip): array
    {
        $customer = $trip->primaryCustomer();

        if ($customer === null) {
            return [];
        }

        $tokens = $customer->devices
            ->filter(fn (UserDevice $device): bool => $device->active && filled($device->fcm_token))
            ->map(fn (UserDevice $device): string => (string) $device->fcm_token)
            ->unique()
            ->values()
            ->all();

        return array_values($tokens);
    }

    private function notifyCustomerOfAcceptance(Trip $trip, User $rider): void
    {
        $this->pushGateway->sendToTokens(
            $this->primaryCustomerDeviceTokens($trip),
            'Your rider is on the way',
            "{$rider->name} has accepted your trip and is heading to the pickup point.",
            [
                'trip_id' => (string) $trip->id,
                'status' => 'accepted',
            ],
        );
    }

    private function notifyCustomerOfArrival(Trip $trip): void
    {
        $this->pushGateway->sendToTokens(
            $this->primaryCustomerDeviceTokens($trip),
            'Your rider has arrived',
            'Your rider is waiting at the pickup point.',
            [
                'trip_id' => (string) $trip->id,
                'status' => 'arrived',
            ],
        );
    }

    private function dispatchToNearbyRiders(Trip $trip, Point $pickup, int $vehicleTypeId, int $excludedUserId, ?string $pickupAddress = null): void
    {
        $radiusMeters = (float) Configuration::get('dispatch_radius_km', 5) * 1000;

        $riderProfiles = RiderProfile::query()
            ->where('availability_status', 'online')
            ->whereNotNull('current_location')
            ->where('user_id', '!=', $excludedUserId)
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

        $isRide = in_array($trip->type, ['ride', 'ride_share'], true);
        $primaryItem = $isRide
            ? ($trip->relationLoaded('passengers') ? $trip->passengers->first() : $trip->passengers()->first())
            : ($trip->relationLoaded('deliveries') ? $trip->deliveries->first() : $trip->deliveries()->first());

        // Ride/ride_share's fare lives on the passenger's fare breakdown now; delivery keeps
        // it on the delivery record directly.
        $estimatedFare = $primaryItem instanceof TripPassenger ? $primaryItem->fareBreakdown?->estimated_fare : $primaryItem?->estimated_fare;
        $currencyCode = $primaryItem instanceof TripPassenger ? $primaryItem->fareBreakdown?->currency_code : $primaryItem?->currency_code;

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
                'currency_code' => (string) $currencyCode,
            ],
        );
    }

    private function notifyCustomerOfRiderCancellation(Trip $trip): void
    {
        $this->pushGateway->sendToTokens(
            $this->primaryCustomerDeviceTokens($trip),
            'Your rider cancelled',
            'Your rider cancelled the trip. We are finding you another rider.',
            [
                'trip_id' => (string) $trip->id,
                'status' => 'searching',
            ],
        );
    }

    private function settleWalletPayment(TripPassenger|DeliveryDetails $settleable, TripFareBreakdown|DeliveryDetails $paymentRecord, RiderProfile $riderProfile, float $finalFare, float $riderEarning, float $commissionAmount): void
    {
        $customerId = $settleable instanceof DeliveryDetails ? $settleable->sender_id : $settleable->customer_id;
        $referenceClass = $settleable::class;

        $customerWallet = Wallet::query()->where('user_id', $customerId)->first();

        if (! $customerWallet) {
            throw ValidationException::withMessages([
                'payment_method' => 'The customer does not have a wallet set up.',
            ]);
        }

        if ((float) $customerWallet->balance < $finalFare) {
            throw ValidationException::withMessages([
                'payment_method' => 'The customer has insufficient wallet balance.',
            ]);
        }

        $customerWallet->decrement('balance', $finalFare);

        Transaction::query()->create([
            'user_id' => $customerId,
            'wallet_id' => $customerWallet->id,
            'method' => 'wallet',
            'direction' => 'debit',
            'transaction_type' => 'trip_payment',
            'amount' => $finalFare,
            'currency_code' => $paymentRecord->currency_code,
            'narration' => "Payment for trip {$settleable->trip->trip_number}",
            'reference_type' => $referenceClass,
            'reference_id' => $settleable->id,
            'status' => 'completed',
        ]);

        $riderWallet = Wallet::query()->where('user_id', $riderProfile->user_id)->first();

        if ($riderWallet) {
            $riderWallet->increment('balance', $riderEarning);
        }

        Transaction::query()->create([
            'user_id' => $riderProfile->user_id,
            'wallet_id' => $riderWallet?->id,
            'method' => 'wallet',
            'direction' => 'credit',
            'transaction_type' => 'trip_payout',
            'amount' => $riderEarning,
            'currency_code' => $paymentRecord->currency_code,
            'narration' => "Payout for trip {$settleable->trip->trip_number}",
            'reference_type' => $referenceClass,
            'reference_id' => $settleable->id,
            'status' => $riderWallet ? 'completed' : 'pending',
        ]);

        $this->transactionService->recordPassengerCommissionTransaction($settleable, 'wallet', $commissionAmount, $paymentRecord->currency_code);

        $paymentRecord->update(['payment_status' => 'paid']);
    }

    private function settleCashPayment(TripPassenger|DeliveryDetails $settleable, TripFareBreakdown|DeliveryDetails $paymentRecord, RiderProfile $riderProfile, float $finalFare, float $riderEarning, float $commissionAmount): void
    {
        $customerId = $settleable instanceof DeliveryDetails ? $settleable->sender_id : $settleable->customer_id;
        $referenceClass = $settleable::class;

        Transaction::query()->create([
            'user_id' => $customerId,
            'wallet_id' => null,
            'method' => 'cash',
            'direction' => 'debit',
            'transaction_type' => 'trip_payment',
            'amount' => $finalFare,
            'currency_code' => $paymentRecord->currency_code,
            'narration' => "Cash payment for trip {$settleable->trip->trip_number}",
            'reference_type' => $referenceClass,
            'reference_id' => $settleable->id,
            'status' => 'completed',
        ]);

        Transaction::query()->create([
            'user_id' => $riderProfile->user_id,
            'wallet_id' => null,
            'method' => 'cash',
            'direction' => 'credit',
            'transaction_type' => 'trip_payout',
            'amount' => $riderEarning,
            'currency_code' => $paymentRecord->currency_code,
            'narration' => "Cash payout for trip {$settleable->trip->trip_number}",
            'reference_type' => $referenceClass,
            'reference_id' => $settleable->id,
            'status' => 'completed',
        ]);

        $this->transactionService->recordPassengerCommissionTransaction($settleable, 'cash', $commissionAmount, $paymentRecord->currency_code);

        $paymentRecord->update(['payment_status' => 'paid']);
    }

    private function settleMobileMoneyPayment(TripPassenger|DeliveryDetails $settleable, TripFareBreakdown|DeliveryDetails $paymentRecord, RiderProfile $riderProfile, float $finalFare, float $riderEarning, float $commissionAmount): void
    {
        $customerId = $settleable instanceof DeliveryDetails ? $settleable->sender_id : $settleable->customer_id;
        $customer = $settleable instanceof DeliveryDetails ? $settleable->sender : $settleable->customer;
        $referenceClass = $settleable::class;

        $riderWallet = Wallet::query()
            ->where('user_id', $riderProfile->user_id)
            ->where('status', 'active')
            ->lockForUpdate()
            ->first();

        if (! $riderWallet) {
            throw ValidationException::withMessages([
                'wallet' => 'You need an active wallet before you can receive a mobile-money trip payout.',
            ]);
        }

        $reference = (string) Str::uuid();
        $result = $this->paymentGateway->collectFromMobileMoney(
            $customer->phone,
            $finalFare,
            $paymentRecord->currency_code,
            $reference,
            "Payment for trip {$settleable->trip->trip_number}",
        );

        $transactionStatus = match ($result->status) {
            MobileMoneyTransactionStatus::Succeeded => 'completed',
            MobileMoneyTransactionStatus::Failed => 'failed',
            MobileMoneyTransactionStatus::Pending, MobileMoneyTransactionStatus::Indeterminate => 'pending',
        };

        Transaction::query()->create([
            'user_id' => $customerId,
            'wallet_id' => null,
            'method' => 'mobile_money',
            'direction' => 'debit',
            'transaction_type' => 'trip_payment',
            'amount' => $finalFare,
            'currency_code' => $paymentRecord->currency_code,
            'gateway' => $this->paymentGateway->name(),
            'gateway_reference' => $result->transactionReference,
            'external_reference' => $reference,
            'network_reference' => $result->gatewayReference,
            'phone' => $customer->phone,
            'narration' => "Mobile-money payment for trip {$settleable->trip->trip_number}",
            'reference_type' => $referenceClass,
            'reference_id' => $settleable->id,
            'status' => $transactionStatus,
            'failure_reason' => $result->failureReason,
        ]);

        $payout = Transaction::query()->create([
            'user_id' => $riderProfile->user_id,
            'wallet_id' => $riderWallet->id,
            'method' => 'mobile_money',
            'direction' => 'credit',
            'transaction_type' => 'trip_payout',
            'amount' => $riderEarning,
            'currency_code' => $paymentRecord->currency_code,
            'narration' => "Mobile-money payout for trip {$settleable->trip->trip_number}",
            'reference_type' => $referenceClass,
            'reference_id' => $settleable->id,
            'status' => $transactionStatus,
            'failure_reason' => $result->failureReason,
        ]);

        if ($result->status === MobileMoneyTransactionStatus::Succeeded) {
            $balanceBefore = (float) $riderWallet->balance;
            $balanceAfter = round($balanceBefore + $riderEarning, 2);

            $riderWallet->update(['balance' => $balanceAfter]);
            $payout->update([
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
            ]);

            $this->transactionService->recordPassengerCommissionTransaction($settleable, 'mobile_money', $commissionAmount, $paymentRecord->currency_code);

            $paymentRecord->update(['payment_status' => 'paid']);

            return;
        }

        $paymentRecord->update(['payment_status' => $result->status === MobileMoneyTransactionStatus::Failed ? 'failed' : 'pending']);
    }

    private function ownRide(User $user, int $tripId): Trip
    {
        return Trip::query()
            ->where('id', $tripId)
            ->where('rider_id', $user->id)
            ->firstOrFail();
    }

    private function riderProfile(User $user): RiderProfile
    {
        $riderProfile = $user->riderProfile;

        if (! $riderProfile) {
            throw ValidationException::withMessages([
                'rider_profile' => 'You need a rider profile before you can manage rides.',
            ]);
        }

        return $riderProfile;
    }
}
