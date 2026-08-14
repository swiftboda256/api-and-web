<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\RiderProfile;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\TripLocation;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Wallet;
use App\Services\Push\FcmGateway;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

readonly class RiderTripService
{
    private const array ACTIVE_STATUSES = ['accepted', 'arrived', 'in_progress'];

    public function __construct(
        private FcmGateway $pushGateway,
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
        $radiusMeters = (float) Configuration::get('search_radius_km', 5) * 1000;

        return Trip::query()
            ->where('status', 'searching')
            ->whereNull('rider_id')
            ->where('vehicle_type_id', $vehicle->vehicle_type_id)
            ->where(ST::distanceSphere('pickup_location', $point), '<=', $radiusMeters)
            ->orderBy(ST::distanceSphere('pickup_location', $point))
            ->with(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer'])
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function show(User $user, int $tripId): Trip
    {
        return Trip::query()
            ->where('rider_id', $user->id)
            ->where('id', $tripId)
            ->with(['customer', 'vehicleType', 'fareBreakdown', 'deliveryDetails', 'cancellationReason'])
            ->firstOrFail();
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

        $trip = Trip::query()
            ->with(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer.devices'])
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

        $trip = $trip->refresh()->load(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'customer.devices']);

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

    public function end(User $user, int $tripId, ?UploadedFile $proofOfDeliveryPhoto = null): Trip
    {
        $riderProfile = $this->riderProfile($user);
        $trip = $this->ownRide($user, $tripId)->load(['fareBreakdown', 'deliveryDetails']);

        if (! in_array($trip->status, self::ACTIVE_STATUSES, true)) {
            throw ValidationException::withMessages([
                'status' => 'This ride cannot be ended from its current status.',
            ]);
        }

        if ($trip->type === 'delivery' && ! $proofOfDeliveryPhoto) {
            throw ValidationException::withMessages([
                'proof_of_delivery_photo' => 'A photo of the delivered package is required to complete this delivery.',
            ]);
        }

        $proofOfDeliveryPhotoUrl = $proofOfDeliveryPhoto
            ? $this->storeProofOfDeliveryPhoto($trip, $proofOfDeliveryPhoto)
            : null;

        return DB::transaction(function () use ($trip, $riderProfile, $proofOfDeliveryPhotoUrl): Trip {
            $finalFare = (float) $trip->estimated_fare;
            $riderEarning = $trip->fareBreakdown !== null ? (float) $trip->fareBreakdown->rider_earning : $finalFare;

            $trip->update([
                'status' => 'completed',
                'completed_at' => now(),
                'final_fare' => $finalFare,
            ]);

            if ($proofOfDeliveryPhotoUrl !== null) {
                $trip->deliveryDetails?->update(['proof_of_delivery_photo' => $proofOfDeliveryPhotoUrl]);
            }

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

    private function notifyCustomerOfAcceptance(Trip $trip, User $rider): void
    {
        $tokens = $trip->customer->devices
            ->filter(fn (UserDevice $device): bool => $device->active && filled($device->fcm_token))
            ->map(fn (UserDevice $device): string => (string) $device->fcm_token)
            ->unique()
            ->values()
            ->all();

        $this->pushGateway->sendToTokens(
            array_values($tokens),
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
        $tokens = $trip->customer->devices
            ->filter(fn (UserDevice $device): bool => $device->active && filled($device->fcm_token))
            ->map(fn (UserDevice $device): string => (string) $device->fcm_token)
            ->unique()
            ->values()
            ->all();

        $this->pushGateway->sendToTokens(
            array_values($tokens),
            'Your rider has arrived',
            'Your rider is waiting at the pickup point.',
            [
                'trip_id' => (string) $trip->id,
                'status' => 'arrived',
            ],
        );
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
