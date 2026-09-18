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
        $carXlTypeId = VehicleType::query()->where('code', 'car_xl')->value('id');

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
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Allion'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Axio'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Passo'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Harrier'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Mark X'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Auris'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Rumion'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'X-Trail'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'Note'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'Tiida'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'Wingroad'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Honda', 'name' => 'Fit'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Honda', 'name' => 'Vezel'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Honda', 'name' => 'CR-V'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Mazda', 'name' => 'Demio'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Mazda', 'name' => 'Axela'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Subaru', 'name' => 'Forester'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Subaru', 'name' => 'Impreza'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Volkswagen', 'name' => 'Passat'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Probox'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Spacio'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Succeed'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Ractis'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Belta'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Yaris'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'IST'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Camry'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Toyota', 'name' => 'Crown'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'Sunny'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'Almera'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'March'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Nissan', 'name' => 'AD Van'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Subaru', 'name' => 'Legacy'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Subaru', 'name' => 'Outback'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Mercedes-Benz', 'name' => 'C-Class'],
            ['vehicle_type_id' => $carTypeId, 'make' => 'Mercedes-Benz', 'name' => 'E-Class'],

            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Toyota', 'name' => 'Voxy'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Toyota', 'name' => 'Alphard'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Toyota', 'name' => 'Estima'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Toyota', 'name' => 'Land Cruiser Prado'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Toyota', 'name' => 'Land Cruiser'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Toyota', 'name' => 'Fortuner'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Nissan', 'name' => 'Serena'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Nissan', 'name' => 'X-Trail'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Mitsubishi', 'name' => 'Pajero'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Honda', 'name' => 'CR-V'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Toyota', 'name' => 'Sienta'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Toyota', 'name' => 'Regius'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Nissan', 'name' => 'Elgrand'],
            ['vehicle_type_id' => $carXlTypeId, 'make' => 'Mitsubishi', 'name' => 'Delica'],
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
