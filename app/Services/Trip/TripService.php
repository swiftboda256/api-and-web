<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\DeliveryDetails;
use App\Models\PromoCodeRedemption;
use App\Models\Rating;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
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

readonly class TripService
{
    public function __construct(
        private FcmGateway $pushGateway,
        private CheckoutService $checkout,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Trip>
     */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        $query = Trip::query()
            ->where('customer_id', $user->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('type', $type))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('requested_at', '>=', CarbonImmutable::parse($from)))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('requested_at', '<=', CarbonImmutable::parse($to)))
            ->with(['vehicleType', 'fareBreakdown', 'deliveryDetails']);

        if (($filters['sort_by_fare'] ?? null) !== null) {
            $query->orderBy('estimated_fare', $filters['sort_by_fare']);
        } else {
            $query->orderByDesc('requested_at');
        }

        return $query->paginate((int) ($filters['per_page'] ?? 15));
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
            'estimated_fare' => $this->checkout->roundFare(round($fare['fare'] - $promoResult['discount'], 2)),
        ];
    }

    public function show(User $user, int $tripId): Trip
    {
        return Trip::query()
            ->where('customer_id', $user->id)
            ->where('id', $tripId)
            ->with(['rider.riderProfile', 'vehicleType', 'fareBreakdown', 'deliveryDetails', 'cancellationReason'])
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function book(User $user, array $data, CarbonImmutable $requestedAt): Trip
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

        $distanceKm = (float) $data['distance_km'];
        $durationMinutes = $this->checkout->durationMinutes($distanceKm);

        $fare = $this->checkout->calculateFare($pricingRule, $zone, (int) $data['vehicle_type_id'], $distanceKm, $durationMinutes, $requestedAt);
        $promoResult = $this->checkout->resolvePromoDiscount($data['promo_code'] ?? null, $user, $zone, (int) $data['vehicle_type_id'], $fare['fare']);

        $total = $this->checkout->roundFare(round($fare['fare'] - $promoResult['discount'], 2));
        $commissionAmount = round($total * ((float) $pricingRule->commission_rate / 100), 2);
        $riderEarning = round($total - $commissionAmount, 2);

        $trip = DB::transaction(function () use ($user, $data, $zone, $pickup, $dropoff, $distanceKm, $durationMinutes, $fare, $promoResult, $total, $commissionAmount, $riderEarning, $pricingRule, $requestedAt): Trip {
            $trip = Trip::query()->create([
                'trip_number' => $this->generateTripNumber(),
                'customer_id' => $user->id,
                'vehicle_type_id' => $data['vehicle_type_id'],
                'zone_id' => $zone->id,
                'type' => $data['type'],
                'status' => 'requested',
                'pickup_location' => $pickup,
                'pickup_address' => $data['pickup_address'] ?? null,
                'dropoff_location' => $dropoff,
                'dropoff_address' => $data['dropoff_address'] ?? null,
                'requested_at' => $requestedAt,
                'distance_km' => $distanceKm,
                'duration_minutes' => $durationMinutes,
                'estimated_fare' => $total,
                'currency_code' => $zone->currency_code,
                'promo_code_id' => $promoResult['promo']?->id,
                'discount_amount' => $promoResult['discount'],
                'payment_method' => $data['payment_method'],
                'payment_status' => 'pending',
            ]);

            TripFareBreakdown::query()->create([
                'trip_id' => $trip->id,
                'base_fare' => $fare['base_fare'],
                'distance_fare' => $fare['distance_fare'],
                'time_fare' => $fare['time_fare'],
                'surge_multiplier' => $fare['surge_multiplier'],
                'surge_amount' => $fare['surge_amount'],
                'discount_amount' => $promoResult['discount'],
                'commission_rate' => $pricingRule->commission_rate,
                'commission_amount' => $commissionAmount,
                'rider_earning' => $riderEarning,
                'total' => $total,
                'currency_code' => $zone->currency_code,
            ]);

            if ($data['type'] === 'delivery') {
                DeliveryDetails::query()->create([
                    'trip_id' => $trip->id,
                    'recipient_name' => $data['recipient_name'],
                    'recipient_phone' => $data['recipient_phone'],
                    'package_description' => $data['package_description'] ?? null,
                    'package_size' => $data['package_size'] ?? 'small',
                    'package_weight_kg' => $data['package_weight_kg'] ?? null,
                    'requires_signature' => $data['requires_signature'] ?? false,
                ]);
            }

            if ($promoResult['promo'] !== null) {
                PromoCodeRedemption::query()->create([
                    'promo_code_id' => $promoResult['promo']->id,
                    'user_id' => $user->id,
                    'trip_id' => $trip->id,
                    'discount_amount' => $promoResult['discount'],
                    'redeemed_at' => now(),
                ]);
            }

            return $trip->load(['vehicleType', 'fareBreakdown', 'deliveryDetails']);
        });

        if (! $requestedAt->isFuture()) {
            $trip->update(['status' => 'searching']);

            try {
                $this->dispatchToNearbyRiders($trip, $pickup, (int) $data['vehicle_type_id']);
            } catch (Throwable $e) {
                Log::error('trip.dispatch_failed', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $trip;
    }

    public function cancel(User $user, int $tripId, ?int $cancellationReasonId): Trip
    {
        $trip = Trip::query()
            ->where('customer_id', $user->id)
            ->where('id', $tripId)
            ->firstOrFail();

        if (in_array($trip->status, ['completed', 'cancelled'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This trip can no longer be cancelled.',
            ]);
        }

        $trip->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancelled_by' => $user->id,
            'cancellation_reason_id' => $cancellationReasonId,
        ]);

        return $trip->refresh()->load(['vehicleType', 'fareBreakdown', 'deliveryDetails', 'cancellationReason']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function rateRider(User $user, int $tripId, array $data): Rating
    {
        $trip = Trip::query()
            ->where('customer_id', $user->id)
            ->where('id', $tripId)
            ->firstOrFail();

        if ($trip->status !== 'completed') {
            throw ValidationException::withMessages([
                'trip' => 'Only completed trips can be rated.',
            ]);
        }

        if ($trip->rider_id === null) {
            throw ValidationException::withMessages([
                'trip' => 'This trip has no assigned rider to rate.',
            ]);
        }

        if (Rating::query()->where('trip_id', $trip->id)->where('rater_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'trip' => 'You have already rated this trip.',
            ]);
        }

        return Rating::query()->create([
            'trip_id' => $trip->id,
            'rater_id' => $user->id,
            'ratee_id' => $trip->rider_id,
            'rater_role' => 'customer',
            'score' => $data['score'],
            'comment' => $data['comment'] ?? null,
            'tags' => $data['tags'] ?? null,
        ]);
    }

    public function dispatchDueTrip(Trip $trip): void
    {
        $this->dispatchToNearbyRiders($trip, $trip->pickup_location, (int) $trip->vehicle_type_id);
    }

    private function dispatchToNearbyRiders(Trip $trip, Point $pickup, int $vehicleTypeId): void
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

        $body = $trip->pickup_address
            ? "New {$trip->type} request near {$trip->pickup_address}"
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
                'estimated_fare' => (string) $trip->estimated_fare,
                'currency_code' => $trip->currency_code,
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
