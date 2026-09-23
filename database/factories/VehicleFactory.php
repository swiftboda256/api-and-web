<?php

namespace Database\Factories;

use App\Models\RiderProfile;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Vehicle>
 */
class VehicleFactory extends Factory
{
    protected $model = Vehicle::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rider_profile_id' => RiderProfile::factory(),
            'vehicle_type_id' => VehicleType::factory(),
            'year' => 2020,
            'color' => 'white',
            'plate_number' => strtoupper(fake()->unique()->bothify('U??? ###?')),
            'registration_number' => null,
            'insurance_expiry_at' => now()->addYear(),
            'status' => 'approved',
        ];
    }
}
