<?php

namespace Database\Factories;

use App\Models\PricingRule;
use App\Models\VehicleType;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PricingRule>
 */
class PricingRuleFactory extends Factory
{
    protected $model = PricingRule::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'base_fare' => 1000,
            'per_km_rate' => 750,
            'per_minute_rate' => 0,
            'minimum_fare' => 1000,
            'cancellation_fee' => 0,
            'commission_rate' => 9,
            'surge_multiplier' => 1,
            'currency_code' => 'UGX',
            'effective_from' => null,
            'effective_to' => null,
            'is_active' => true,
        ];
    }
}
