<?php

namespace Database\Factories;

use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RiderProfile>
 */
class RiderProfileFactory extends Factory
{
    protected $model = RiderProfile::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'rider_ref' => strtoupper(fake()->unique()->bothify('RID-####')),
            'kyc_status' => 'approved',
            'approved_at' => now(),
            'availability_status' => 'offline',
            'current_location' => null,
            'total_trips' => 0,
            'total_earnings' => 0,
        ];
    }
}
