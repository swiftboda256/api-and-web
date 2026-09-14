<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\VehicleModel;
use App\Models\VehicleType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class VehicleModelSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $motorcycleTypeId = VehicleType::query()->where('code', 'motorcycle')->value('id');
        $carTypeId = VehicleType::query()->where('code', 'car')->value('id');

        $vehicleModels = [
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'Bajaj', 'name' => 'Boxer 150'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'Bajaj', 'name' => 'Boxer BM150'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'Bajaj', 'name' => 'Pulsar 150'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'TVS', 'name' => 'Star HLX 125'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'TVS', 'name' => 'Sporty'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'Honda', 'name' => 'CG 125'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'Haojue', 'name' => 'DF150'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'Haojue', 'name' => 'Ace'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'Yamaha', 'name' => 'YBR125'],
            ['vehicle_type_id' => $motorcycleTypeId, 'make' => 'Suzuki', 'name' => 'GN125'],

            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Corolla'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Premio'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Vitz'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Wish'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Noah'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Fielder'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Hiace'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'RAV4'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'X-Trail'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Subaru', 'name' => 'Forester'],
        ];

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            foreach ($vehicleModels as $vehicleModel) {
                VehicleModel::query()->firstOrCreate(
                    ['vehicle_type_id' => $vehicleModel['vehicle_type_id'], 'make' => $vehicleModel['make'], 'name' => $vehicleModel['name']],
                    ['is_active' => true],
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
