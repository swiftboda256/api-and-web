<?php

namespace App\Services\Checkout;

use App\Models\Configuration;
use App\Models\DeliveryDetails;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\SurgePricingSchedule;
use App\Models\TripPassenger;
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
            // Zones can overlap (e.g. Kampala inside the surrounding districts). The
            // smallest, most specific zone wins (area rounded to km² so float noise
            // doesn't decide); among equal-sized zones, the one centred nearest the pickup.
            ->orderByRaw('ROUND(ST_Area(boundary::geography) / 1000000)')
            ->orderByRaw(
                'ST_Distance(ST_Centroid(boundary)::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography)',
                [$pickup->getLongitude(), $pickup->getLatitude()],
            )
            ->orderBy('id')
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
        bool $applySurge = true,
    ): array {
        $surgeSchedule = $applySurge ? $this->resolveSurgeSchedule($zone, $vehicleTypeId, $at) : null;

        // During a surge window the schedule's fixed_amount replaces the pricing rule's base fare.
        $baseFare = $surgeSchedule && (float) $surgeSchedule->fixed_amount > 0
            ? (float) $surgeSchedule->fixed_amount
            : (float) $pricingRule->base_fare;
        $freeDistanceKm = (float) $pricingRule->vehicleType->free_distance_km;
        $chargeableDistanceKm = max(0.0, $distanceKm - $freeDistanceKm);
        // During a surge window the schedule's per_km_rate (when set) replaces the pricing rule's.
        $perKmRate = $surgeSchedule && $surgeSchedule->per_km_rate !== null
            ? (float) $surgeSchedule->per_km_rate
            : (float) $pricingRule->per_km_rate;
        $distanceFare = round($perKmRate * $chargeableDistanceKm, 2);
        $timeFare = round((float) $pricingRule->per_minute_rate * $durationMinutes, 2);
        $subtotal = $baseFare + $distanceFare + $timeFare;
        $fare = max($subtotal, (float) $pricingRule->minimum_fare);

        return [
            'base_fare' => $baseFare,
            'distance_fare' => $distanceFare,
            'time_fare' => $timeFare,
            // Kept for the trip_fare_breakdowns columns and API keys; multiplier surge no longer exists.
            'surge_multiplier' => 1.0,
            'surge_amount' => 0.0,
            'fare' => round($fare, 2),
        ];
    }

    /**
     * Fare for a single passenger's own pickup -> dropoff segment on a ride-share trip:
     * the same base+distance+time+surge fare as a solo ride over that segment, discounted
     * by the configured ride_share_discount_percentage, floored at the pricing rule's
     * minimum fare. Ride-share fares do not stack with promo codes.
     *
     * @return array{base_fare: float, distance_fare: float, time_fare: float, surge_multiplier: float, surge_amount: float, discount_percentage: float, fare: float}
     */
    public function calculateRideShareFare(
        PricingRule $pricingRule,
        Zone $zone,
        int $vehicleTypeId,
        float $distanceKm,
        int $durationMinutes,
        CarbonInterface $at,
    ): array {
        $soloFare = $this->calculateFare($pricingRule, $zone, $vehicleTypeId, $distanceKm, $durationMinutes, $at);

        $discountPercentage = (float) Configuration::get('ride_share_discount_percentage', 0);
        $discountedFare = max(
            $soloFare['fare'] * (1 - $discountPercentage / 100),
            (float) $pricingRule->minimum_fare,
        );

        return [
            ...$soloFare,
            'discount_percentage' => $discountPercentage,
            'fare' => round($discountedFare, 2),
        ];
    }

    /**
     * Fare for a single delivery's own pickup -> dropoff segment on a pooled delivery trip:
     * the same base+distance+time+surge fare as a standalone delivery over that segment,
     * discounted by the configured delivery_share_discount_percentage, floored at the
     * pricing rule's minimum fare. Pooled-delivery fares do not stack with promo codes.
     *
     * @return array{base_fare: float, distance_fare: float, time_fare: float, surge_multiplier: float, surge_amount: float, discount_percentage: float, fare: float}
     */
    public function calculateDeliveryShareFare(
        PricingRule $pricingRule,
        Zone $zone,
        int $vehicleTypeId,
        float $distanceKm,
        int $durationMinutes,
        CarbonInterface $at,
    ): array {
        $soloFare = $this->calculateFare($pricingRule, $zone, $vehicleTypeId, $distanceKm, $durationMinutes, $at);

        $discountPercentage = (float) Configuration::get('delivery_share_discount_percentage', 0);
        $discountedFare = max(
            $soloFare['fare'] * (1 - $discountPercentage / 100),
            (float) $pricingRule->minimum_fare,
        );

        return [
            ...$soloFare,
            'discount_percentage' => $discountPercentage,
            'fare' => round($discountedFare, 2),
        ];
    }

    /**
     * Recalculates a passenger's or delivery's final fare at drop-off -- branches on the
     * parent trip's type since 'ride_share'/'delivery_share' apply their own discount
     * percentage while solo 'ride'/'delivery' don't; none of the four re-apply a promo code
     * discount here, matching recalculateFare()'s existing behavior of recomputing the raw
     * fare rather than reapplying promos.
     *
     * Returns the full breakdown (not just the final figure) so the caller can record it for
     * reference -- 'fare' is the rounded final fare, 'fare_before_rounding' is what it was
     * before roundFare() ran.
     *
     * @return array{base_fare: float, distance_fare: float, time_fare: float, surge_multiplier: float, surge_amount: float, fare_before_rounding: float, fare: float}
     */
    public function recalculatePassengerFare(TripPassenger|DeliveryDetails $settleable): array
    {
        $trip = $settleable->trip;

        if (! $trip->zone_id || ! $trip->zone) {
            return $this->unresolvedFareBreakdown($settleable);
        }

        $pricingRule = $this->findPricingRule($trip->zone_id, (int) $trip->vehicle_type_id);

        if (! $pricingRule) {
            return $this->unresolvedFareBreakdown($settleable);
        }

        $fare = match ($trip->type) {
            'ride_share' => $this->calculateRideShareFare($pricingRule, $trip->zone, (int) $trip->vehicle_type_id, (float) $settleable->distance_km, (int) $settleable->duration_minutes, now()),
            'delivery_share' => $this->calculateDeliveryShareFare($pricingRule, $trip->zone, (int) $trip->vehicle_type_id, (float) $settleable->distance_km, (int) $settleable->duration_minutes, now()),
            default => $this->calculateFare($pricingRule, $trip->zone, (int) $trip->vehicle_type_id, (float) $settleable->distance_km, (int) $settleable->duration_minutes, now()),
        };

        return [
            ...$fare,
            'fare_before_rounding' => $fare['fare'],
            'fare' => $this->roundFare($fare['fare']),
        ];
    }

    /**
     * Fallback for recalculatePassengerFare() when the trip's zone/pricing rule can no
     * longer be resolved -- falls back to the fare already recorded at booking, with no
     * breakdown to recompute.
     *
     * @return array{base_fare: float, distance_fare: float, time_fare: float, surge_multiplier: float, surge_amount: float, fare_before_rounding: float, fare: float}
     */
    private function unresolvedFareBreakdown(TripPassenger|DeliveryDetails $settleable): array
    {
        $fare = (float) ($settleable instanceof DeliveryDetails ? $settleable->estimated_fare : $settleable->fareBreakdown?->estimated_fare);
        $baseFare = (float) ($settleable instanceof DeliveryDetails ? $settleable->base_fare_amount : $settleable->fareBreakdown?->base_fare);

        return [
            'base_fare' => $baseFare,
            'distance_fare' => 0.0,
            'time_fare' => 0.0,
            'surge_multiplier' => 1.0,
            'surge_amount' => 0.0,
            'fare_before_rounding' => $fare,
            'fare' => $this->roundFare($fare),
        ];
    }

    /**
     * Validates a promo code independent of any specific trip (activity window and usage limits only).
     */
    public function resolvePromoCode(string $code, User $user): PromoCode
    {
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

        if ($promo->usage_limit_total !== null && $promo->redemptions()->count() >= $promo->usage_limit_total) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code has reached its usage limit.']);
        }

        if ($promo->usage_limit_per_user !== null
            && $promo->redemptions()->where('user_id', $user->id)->count() >= $promo->usage_limit_per_user) {
            throw ValidationException::withMessages(['promo_code' => 'You have already used this promo code.']);
        }

        return $promo;
    }

    /**
     * @return array{promo: ?PromoCode, discount: float}
     */
    public function resolvePromoDiscount(?string $code, User $user, Zone $zone, int $vehicleTypeId, float $fare): array
    {
        if (! $code) {
            return ['promo' => null, 'discount' => 0.0];
        }

        $promo = $this->resolvePromoCode($code, $user);

        if ($promo->min_trip_amount && $fare < (float) $promo->min_trip_amount) {
            throw ValidationException::withMessages(['promo_code' => 'This trip does not meet the minimum amount required for this promo code.']);
        }

        if ($promo->applicable_vehicle_types && ! in_array($vehicleTypeId, $promo->applicable_vehicle_types, true)) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code does not apply to the selected vehicle type.']);
        }

        if ($promo->applicable_zone_ids && ! in_array($zone->id, $promo->applicable_zone_ids, true)) {
            throw ValidationException::withMessages(['promo_code' => 'This promo code does not apply in your area.']);
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

    public function findPricingRule(int $zoneId, int $vehicleTypeId, ?CarbonInterface $at = null): ?PricingRule
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
        $time = $localTime->format('H:i:s');
        $today = $localTime->dayOfWeek;
        $yesterday = ($today + 6) % 7;

        // A null day_of_week means every day.
        $onDay = fn ($query, int $day) => $query->where(fn ($query) => $query->whereNull('day_of_week')->orWhere('day_of_week', $day));

        return SurgePricingSchedule::query()
            ->where('zone_id', $zone->id)
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('vehicle_type_id')->orWhere('vehicle_type_id', $vehicleTypeId))
            ->where(fn ($query) => $query
                // Same-day window (e.g. 07:00-09:00) on today.
                ->where(fn ($query) => $onDay($query->whereColumn('start_time', '<=', 'end_time'), $today)
                    ->whereTime('start_time', '<=', $time)
                    ->whereTime('end_time', '>=', $time))
                // Overnight window (e.g. 22:30-05:30) that started today: before midnight.
                ->orWhere(fn ($query) => $onDay($query->whereColumn('start_time', '>', 'end_time'), $today)
                    ->whereTime('start_time', '<=', $time))
                // Overnight window that started yesterday: after midnight.
                ->orWhere(fn ($query) => $onDay($query->whereColumn('start_time', '>', 'end_time'), $yesterday)
                    ->whereTime('end_time', '>=', $time)))
            ->orderByDesc('fixed_amount')
            ->first();
    }
}
