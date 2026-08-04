<?php

namespace App\Services\Trip;

use App\Models\DeliveryDetails;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\PromoCodeRedemption;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Zone;
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
    private const float AVERAGE_SPEED_KMH = 25.0;

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
        $dropoff = Point::makeGeodetic((float) $data['dropoff_latitude'], (float) $data['dropoff_longitude']);

        $zone = $this->resolveZone($pickup);
        $pricingRule = $this->resolvePricingRule($zone->id, (int) $data['vehicle_type_id']);

        $distanceKm = $this->distanceKm($pickup, $dropoff);
        $durationMinutes = $this->durationMinutes($distanceKm);

        $fare = $this->calculateFare($pricingRule, $distanceKm, $durationMinutes);
        $promoResult = $this->resolvePromoDiscount($data['promo_code'] ?? null, $user, $zone, (int) $data['vehicle_type_id'], $fare['fare']);

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
            'estimated_fare' => round($fare['fare'] - $promoResult['discount'], 2),
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
        $pickup = Point::makeGeodetic((float) $data['pickup_latitude'], (float) $data['pickup_longitude']);
        $dropoff = Point::makeGeodetic((float) $data['dropoff_latitude'], (float) $data['dropoff_longitude']);

        $zone = $this->resolveZone($pickup);
        $pricingRule = $this->resolvePricingRule($zone->id, (int) $data['vehicle_type_id']);

        $distanceKm = $this->distanceKm($pickup, $dropoff);
        $durationMinutes = $this->durationMinutes($distanceKm);

        $fare = $this->calculateFare($pricingRule, $distanceKm, $durationMinutes);
        $promoResult = $this->resolvePromoDiscount($data['promo_code'] ?? null, $user, $zone, (int) $data['vehicle_type_id'], $fare['fare']);

        $total = round($fare['fare'] - $promoResult['discount'], 2);
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

    private function dispatchToNearbyRiders(Trip $trip, Point $pickup, int $vehicleTypeId): void
    {
        $radiusMeters = (float) config('trip.dispatch_radius_km') * 1000;

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

    private function resolveZone(Point $pickup): Zone
    {
        $zone = Zone::query()
            ->where(ST::contains('boundary', $pickup), true)
            ->where('is_active', true)
            ->first();

        if (! $zone) {
            throw ValidationException::withMessages([
                'pickup_latitude' => 'We do not currently operate in this area.',
            ]);
        }

        return $zone;
    }

    private function resolvePricingRule(int $zoneId, int $vehicleTypeId): PricingRule
    {
        $pricingRule = PricingRule::query()
            ->where('zone_id', $zoneId)
            ->where('vehicle_type_id', $vehicleTypeId)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', now()))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', now()))
            ->orderByDesc('effective_from')
            ->first();

        if (! $pricingRule) {
            throw ValidationException::withMessages([
                'vehicle_type_id' => 'This vehicle type is not available in your area right now.',
            ]);
        }

        return $pricingRule;
    }

    private function distanceKm(Point $from, Point $to): float
    {
        $meters = (float) DB::query()
            ->select([ST::distanceSphere($from, $to)->as('distance')])
            ->value('distance');

        return round($meters / 1000, 2);
    }

    private function durationMinutes(float $distanceKm): int
    {
        return (int) ceil(($distanceKm / self::AVERAGE_SPEED_KMH) * 60);
    }

    /**
     * @return array{base_fare: float, distance_fare: float, time_fare: float, surge_multiplier: float, surge_amount: float, fare: float}
     */
    private function calculateFare(PricingRule $pricingRule, float $distanceKm, int $durationMinutes): array
    {
        $baseFare = (float) $pricingRule->base_fare;
        $distanceFare = round((float) $pricingRule->per_km_rate * $distanceKm, 2);
        $timeFare = round((float) $pricingRule->per_minute_rate * $durationMinutes, 2);
        $surgeMultiplier = (float) $pricingRule->surge_multiplier ?: 1.0;

        $subtotal = $baseFare + $distanceFare + $timeFare;
        $surgeAmount = round($subtotal * ($surgeMultiplier - 1), 2);
        $fare = max($subtotal + $surgeAmount, (float) $pricingRule->minimum_fare);

        return [
            'base_fare' => $baseFare,
            'distance_fare' => $distanceFare,
            'time_fare' => $timeFare,
            'surge_multiplier' => $surgeMultiplier,
            'surge_amount' => $surgeAmount,
            'fare' => round($fare, 2),
        ];
    }

    /**
     * @return array{promo: ?PromoCode, discount: float}
     */
    private function resolvePromoDiscount(?string $code, User $user, Zone $zone, int $vehicleTypeId, float $fare): array
    {
        if (! $code) {
            return ['promo' => null, 'discount' => 0.0];
        }

        $promo = PromoCode::query()->where('code', $code)->where('is_active', true)->first();

        if (! $promo) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code is invalid.']);
        }

        if ($promo->valid_from && $promo->valid_from->isFuture()) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code is not yet active.']);
        }

        if ($promo->valid_until && $promo->valid_until->isPast()) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code has expired.']);
        }

        if ($promo->min_trip_amount && $fare < (float) $promo->min_trip_amount) {
            throw ValidationException::withMessages(['promo_code' => 'This trip does not meet the minimum amount required for this promo code.']);
        }

        if ($promo->applicable_vehicle_types && ! in_array($vehicleTypeId, $promo->applicable_vehicle_types, true)) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code does not apply to the selected vehicle type.']);
        }

        if ($promo->applicable_zone_ids && ! in_array($zone->id, $promo->applicable_zone_ids, true)) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code does not apply in your area.']);
        }

        if ($promo->usage_limit_total !== null && $promo->redemptions()->count() >= $promo->usage_limit_total) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code has reached its usage limit.']);
        }

        if ($promo->usage_limit_per_user !== null
            && $promo->redemptions()->where('user_id', $user->id)->count() >= $promo->usage_limit_per_user) {
            throw ValidationException::withMessages(['promo_code' => 'You have already used this promo code.']);
        }

        $discount = $promo->discount_type === 'percentage'
            ? $fare * ((float) $promo->discount_value / 100)
            : (float) $promo->discount_value;

        if ($promo->max_discount_amount !== null) {
            $discount = min($discount, (float) $promo->max_discount_amount);
        }

        return ['promo' => $promo, 'discount' => min(round($discount, 2), $fare)];
    }

    private function generateTripNumber(): string
    {
        do {
            $tripNumber = 'TRP-'.now()->format('ymd').strtoupper(Str::random(6));
        } while (Trip::query()->where('trip_number', $tripNumber)->exists());

        return $tripNumber;
    }
}
