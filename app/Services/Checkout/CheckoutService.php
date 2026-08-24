<?php

namespace App\Services\Checkout;

use App\Models\Configuration;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\SurgePricingSchedule;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use Carbon\CarbonInterface;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Validation\ValidationException;

readonly class CheckoutService
{
    private const float AVERAGE_SPEED_KMH = 25.0;

    public function resolveZone(Point $pickup): Zone
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

    public function resolvePricingRule(int $zoneId, int $vehicleTypeId): PricingRule
    {
        $pricingRule = $this->findPricingRule($zoneId, $vehicleTypeId);

        if (! $pricingRule) {
            throw ValidationException::withMessages([
                'vehicle_type_id' => 'This vehicle type is not available in your area right now.',
            ]);
        }

        return $pricingRule;
    }

    public function durationMinutes(float $distanceKm): int
    {
        return (int) ceil(($distanceKm / self::AVERAGE_SPEED_KMH) * 60);
    }

    /**
     * @return array{base_fare: float, distance_fare: float, time_fare: float, surge_multiplier: float, surge_amount: float, fare: float}
     */
    public function calculateFare(
        PricingRule $pricingRule,
        Zone $zone,
        int $vehicleTypeId,
        float $distanceKm,
        int $durationMinutes,
        CarbonInterface $at,
    ): array {
        $surgeSchedule = $this->resolveSurgeSchedule($zone, $vehicleTypeId, $at);

        $baseFare = (float) $pricingRule->base_fare;
        $perKmRate = (float) $pricingRule->per_km_rate + (float) ($surgeSchedule->fixed_amount ?? 0);
        $distanceFare = round($perKmRate * $distanceKm, 2);
        $timeFare = round((float) $pricingRule->per_minute_rate * $durationMinutes, 2);
        $surgeMultiplier = ((float) $pricingRule->surge_multiplier ?: 1.0) * ((float) ($surgeSchedule->multiplier ?? 1.0) ?: 1.0);

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
    public function resolvePromoDiscount(?string $code, User $user, Zone $zone, int $vehicleTypeId, float $fare): array
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

    public function roundFare(float $fare): float
    {
        if (! Configuration::get('round_fare_to_nearest_500', false)) {
            return $fare;
        }

        return floor($fare / 500) * 500;
    }

    public function recalculateFare(Trip $trip): float
    {
        if (! $trip->zone_id || ! $trip->zone) {
            return $this->roundFare((float) $trip->estimated_fare);
        }

        $pricingRule = $this->findPricingRule($trip->zone_id, (int) $trip->vehicle_type_id);

        if (! $pricingRule) {
            return $this->roundFare((float) $trip->estimated_fare);
        }

        $fare = $this->calculateFare(
            $pricingRule,
            $trip->zone,
            (int) $trip->vehicle_type_id,
            (float) $trip->distance_km,
            (int) $trip->duration_minutes,
            now(),
        );

        return $this->roundFare($fare['fare']);
    }

    private function findPricingRule(int $zoneId, int $vehicleTypeId, ?CarbonInterface $at = null): ?PricingRule
    {
        $at ??= now();

        return PricingRule::query()
            ->where('zone_id', $zoneId)
            ->where('vehicle_type_id', $vehicleTypeId)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('effective_from')->orWhere('effective_from', '<=', $at))
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhere('effective_to', '>=', $at))
            ->orderByDesc('effective_from')
            ->first();
    }

    private function resolveSurgeSchedule(Zone $zone, int $vehicleTypeId, CarbonInterface $at): ?SurgePricingSchedule
    {
        $localTime = $at->clone()->setTimezone($zone->timezone ?? config('app.timezone'));

        return SurgePricingSchedule::query()
            ->where('zone_id', $zone->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('vehicle_type_id')->orWhere('vehicle_type_id', $vehicleTypeId))
            ->where(fn ($query) => $query->whereNull('day_of_week')->orWhere('day_of_week', $localTime->dayOfWeek))
            ->whereTime('start_time', '<=', $localTime->format('H:i:s'))
            ->whereTime('end_time', '>=', $localTime->format('H:i:s'))
            ->orderByDesc('multiplier')
            ->orderByDesc('fixed_amount')
            ->first();
    }
}
