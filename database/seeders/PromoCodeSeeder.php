<?php

namespace Database\Seeders;

use App\Models\PromoCode;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class PromoCodeSeeder extends Seeder
{
    public function run(): void
    {
        $motorcycleId = VehicleType::query()->where('code', 'motorcycle')->value('id');

        $promoCodes = [
            [
                'code' => 'WELCOME10',
                'description' => '10% off your first trip',
                'discount_type' => 'percentage',
                'discount_value' => 10,
                'max_discount_amount' => 3000,
                'min_trip_amount' => null,
                'usage_limit_total' => null,
                'usage_limit_per_user' => 1,
                'applicable_vehicle_types' => null,
                'applicable_zone_ids' => null,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'SAVE2000',
                'description' => 'UGX 2,000 off trips above UGX 5,000',
                'discount_type' => 'fixed',
                'discount_value' => 2000,
                'max_discount_amount' => null,
                'min_trip_amount' => 5000,
                'usage_limit_total' => 1000,
                'usage_limit_per_user' => 3,
                'applicable_vehicle_types' => null,
                'applicable_zone_ids' => null,
                'valid_from' => now(),
                'valid_until' => now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'BODA50',
                'description' => '50% off motorcycle rides, up to UGX 2,000',
                'discount_type' => 'percentage',
                'discount_value' => 50,
                'max_discount_amount' => 2000,
                'min_trip_amount' => null,
                'usage_limit_total' => null,
                'usage_limit_per_user' => 1,
                'applicable_vehicle_types' => $motorcycleId ? [$motorcycleId] : null,
                'applicable_zone_ids' => null,
                'valid_from' => now(),
                'valid_until' => now()->addMonth(),
                'is_active' => true,
            ],
        ];

        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            foreach ($promoCodes as $promoCode) {
                PromoCode::query()->firstOrCreate(
                    ['code' => $promoCode['code']],
                    collect($promoCode)->except('code')->all(),
                );
            }
        } finally {
            if ($actingUser instanceof User) {
                Auth::setUser($actingUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
