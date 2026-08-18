<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use App\Models\VehicleType;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class PricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rates = [
            'motorcycle' => [
                'base_fare' => 1000,
                'per_km_rate' => 750,
                'per_minute_rate' => 0,
                'minimum_fare' => 1000,
                'cancellation_fee' => 0,
                'commission_rate' => 9,
            ],
            'car' => [
                'base_fare' => 1000,
                'per_km_rate' => 750,
                'per_minute_rate' => 0,
                'minimum_fare' => 1000,
                'cancellation_fee' => 0,
                'commission_rate' => 9,
            ],
        ];

        $zones = Zone::query()->get();
        $vehicleTypes = VehicleType::query()->whereIn('code', array_keys($rates))->get();

        foreach ($zones as $zone) {
            foreach ($vehicleTypes as $vehicleType) {
                $rate = $rates[$vehicleType->code];

                PricingRule::query()->firstOrCreate(
                    ['zone_id' => $zone->id, 'vehicle_type_id' => $vehicleType->id],
                    [
                        'base_fare' => $rate['base_fare'],
                        'per_km_rate' => $rate['per_km_rate'],
                        'per_minute_rate' => $rate['per_minute_rate'],
                        'minimum_fare' => $rate['minimum_fare'],
                        'cancellation_fee' => $rate['cancellation_fee'],
                        'commission_rate' => $rate['commission_rate'],
                        'surge_multiplier' => 1,
                        'currency_code' => 'UGX',
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
