<?php

namespace App\Services\Trip;

use App\Models\RiderProfile;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\User;
use App\Models\Wallet;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

readonly class RiderTripService
{
    private const array ACTIVE_STATUSES = ['accepted', 'arrived', 'in_progress'];

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
            ->when($filters['min_fare'] ?? null, fn ($query, $minFare) => $query->whereHas(
                'fareBreakdown', fn ($query) => $query->where('rider_earning', '>=', $minFare)
            ))
            ->when($filters['max_fare'] ?? null, fn ($query, $maxFare) => $query->whereHas(
                'fareBreakdown', fn ($query) => $query->where('rider_earning', '<=', $maxFare)
            ))
            ->with(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer'])
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
        $radiusMeters = (float) config('trip.dispatch_radius_km') * 1000;

        return Trip::query()
            ->where('status', 'searching')
            ->whereNull('rider_id')
            ->where('vehicle_type_id', $vehicle->vehicle_type_id)
            ->where(ST::distanceSphere('pickup_location', $point), '<=', $radiusMeters)
            ->orderBy(ST::distanceSphere('pickup_location', $point))
            ->with(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer'])
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function accept(User $user, int $tripId): Trip
    {
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

        return Trip::query()
            ->with(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer'])
            ->findOrFail($tripId);
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

        return $trip->refresh()->load(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer']);
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

        $trip->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'cancellation_reason_id' => $cancellationReasonId,
        ]);

        $riderProfile->update(['availability_status' => 'online']);

        return $trip->refresh()->load(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer', 'cancellationReason']);
    }

    public function end(User $user, int $tripId): Trip
    {
        $riderProfile = $this->riderProfile($user);
        $trip = $this->ownRide($user, $tripId)->load('fareBreakdown');

        if (! in_array($trip->status, self::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This ride cannot be ended from its current status.',
            ]);
        }

        return DB::transaction(function () use ($trip, $riderProfile): Trip {
            $finalFare = (float) $trip->estimated_fare;
            $riderEarning = $trip->fareBreakdown !== null ? (float) $trip->fareBreakdown->rider_earning : $finalFare;

            $trip->update([
                'status' => 'completed',
                'completed_at' => now(),
                'final_fare' => $finalFare,
            ]);

            if ($trip->payment_method === 'wallet') {
                $this->settleWalletPayment($trip, $riderProfile, $finalFare, $riderEarning);
            }

            $riderProfile->increment('total_trips');
            $riderProfile->increment('total_earnings', $riderEarning);
            $riderProfile->update(['availability_status' => 'online']);

            return $trip->fresh()->load(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer']);
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

    private function settleWalletPayment(Trip $trip, RiderProfile $riderProfile, float $finalFare, float $riderEarning): void
    {
        $customerWallet = Wallet::query()->where('user_id', $trip->customer_id)->first();

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
            'user_id' => $trip->customer_id,
            'wallet_id' => $customerWallet->id,
            'method' => 'wallet',
            'direction' => 'debit',
            'transaction_type' => 'trip_payment',
            'amount' => $finalFare,
            'currency_code' => $trip->currency_code,
            'narration' => "Payment for trip {$trip->trip_number}",
            'reference_type' => Trip::class,
            'reference_id' => $trip->id,
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
            'currency_code' => $trip->currency_code,
            'narration' => "Payout for trip {$trip->trip_number}",
            'reference_type' => Trip::class,
            'reference_id' => $trip->id,
            'status' => $riderWallet ? 'completed' : 'pending',
        ]);

        $trip->update(['payment_status' => 'paid']);
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
