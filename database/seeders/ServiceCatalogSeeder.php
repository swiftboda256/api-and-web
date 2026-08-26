<?php

namespace Database\Seeders;

use App\Models\ServiceCatalog;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class ServiceCatalogSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $motorcycle = VehicleType::query()->where('code', 'motorcycle')->first();
        $car = VehicleType::query()->where('code', 'car')->first();

        $services = [
            ['code' => 'swift-boda', 'name' => 'SWIFT BODA', 'service_type' => 'ride', 'vehicle_types' => [$motorcycle]],
            ['code' => 'swift-car', 'name' => 'SWIFT CAR', 'service_type' => 'ride', 'vehicle_types' => [$car]],
            ['code' => 'swift-delivery', 'name' => 'SWIFT DELIVERY', 'service_type' => 'delivery', 'vehicle_types' => [$motorcycle, $car]],
        ];

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            foreach ($services as $service) {
                $serviceCatalog = ServiceCatalog::query()->firstOrCreate(
                    ['code' => $service['code']],
                    [
                        'name' => $service['name'],
                        'service_type' => $service['service_type'],
                        'is_active' => true,
                    ],
                );

                $vehicleTypeIds = collect($service['vehicle_types'])->filter()->pluck('id');
                $serviceCatalog->vehicleTypes()->sync($vehicleTypeIds);
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
