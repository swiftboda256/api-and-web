<?php

namespace Database\Seeders;

use App\Models\VehicleType;
use Illuminate\Database\Seeder;

class VehicleTypeSeeder extends Seeder
{
    public function run(): void
    {
        $vehicleTypes = [
            ['name' => 'Motorcycle', 'code' => 'motorcycle', 'capacity' => 1],
            ['name' => 'Car', 'code' => 'car', 'capacity' => 4],
        ];

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
    }
}
