<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $vehicleTypes = [
            ['name' => 'Motorcycle', 'code' => 'motorcycle', 'capacity' => 1],
            ['name' => 'Car', 'code' => 'car', 'capacity' => 4],
        ];

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            foreach ($vehicleTypes as $vehicleType) {
                VehicleType::query()->firstOrCreate(
                    ['code' => $vehicleType['code']],
                    [
                        'name' => $vehicleType['name'],
                        'capacity' => $vehicleType['capacity'],
                        'is_active' => true,
                    ],
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
