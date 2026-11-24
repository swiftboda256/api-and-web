<?php

namespace App\Services\Pricing;

use App\Models\PricingRule;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class PricingRuleService
{
    /** What "the same pricing" means: everything except the zone, vehicle type and active state. */
    private const array PRICING_ATTRIBUTES = [
        'base_fare',
        'per_km_rate',
        'per_minute_rate',
        'minimum_fare',
        'cancellation_fee',
        'commission_rate',
        'surge_multiplier',
        'currency_code',
        'effective_from',
        'effective_to',
        'charge_cancellations',
    ];

    /**
     * Copy a rule's pricing to other zones for the same vehicle type. A zone's existing rule is
     * updated (keeping its active state), a deleted one is restored, and a missing one is created.
     *
     * @param  array<int, int|string>  $zoneIds
     * @return array{created: int, updated: int}
     */
    public function applyToZones(PricingRule $source, array $zoneIds): array
    {
        return DB::transaction(function () use ($source, $zoneIds): array {
            $result = ['created' => 0, 'updated' => 0];
            $zoneIds = array_diff(array_unique(array_map(intval(...), $zoneIds)), [(int) $source->zone_id]);

            foreach ($zoneIds as $zoneId) {
                $rule = PricingRule::withTrashed()
                    ->where('zone_id', $zoneId)
                    ->where('vehicle_type_id', $source->vehicle_type_id)
                    ->orderByRaw('deleted_at is not null')
                    ->orderByDesc('id')
                    ->first() ?? new PricingRule(['zone_id' => $zoneId, 'vehicle_type_id' => $source->vehicle_type_id]);

                $isNew = ! $rule->exists || $rule->trashed();

                $rule->fill($source->only(self::PRICING_ATTRIBUTES));

                if ($isNew) {
                    $rule->fill(['is_active' => $source->is_active]);
                    $rule->setAttribute('deleted_at', null);
                    $rule->setAttribute('deleted_by', null);
                }

                $rule->save();

                $result[$isNew ? 'created' : 'updated']++;
            }

            return $result;
        });
    }

    /**
     * Set the given prices on several rules, all or nothing; deleted rules are skipped.
     *
     * @param  array<int, int|string>  $ruleIds
     * @param  array<string, mixed>  $changes
     */
    public function updatePricing(array $ruleIds, array $changes): int
    {
        return DB::transaction(function () use ($ruleIds, $changes): int {
            // Saved one by one so updated_by is stamped.
            $rules = PricingRule::query()->whereKey($ruleIds)->get();

            foreach ($rules as $rule) {
                $rule->update(Arr::only($changes, self::PRICING_ATTRIBUTES));
            }

            return $rules->count();
        });
    }
}
