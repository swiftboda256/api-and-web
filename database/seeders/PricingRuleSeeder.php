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
                'per_km_rate' => 500,
                'per_minute_rate' => 100,
                'minimum_fare' => 2000,
                'cancellation_fee' => 500,
                'commission_rate' => 20,
            ],
            'car' => [
                'base_fare' => 2500,
                'per_km_rate' => 900,
                'per_minute_rate' => 150,
                'minimum_fare' => 5000,
                'cancellation_fee' => 1000,
                'commission_rate' => 20,
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
