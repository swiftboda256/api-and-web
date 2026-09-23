<?php

namespace Database\Factories;

use App\Models\Trip;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Zone;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds a trip in the pre-unification, trip-level-customer-fields shape (customer_id,
 * pickup/dropoff, fare, payment all on the trips row) -- i.e. what an already-existing
 * "legacy" trip looks like before the trip_passengers/delivery_details backfill runs.
 *
 * @extends Factory<Trip>
 */
class TripFactory extends Factory
{
    protected $model = Trip::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $requestedAt = fake()->dateTimeBetween('-30 days', '-1 day');

        return [
            'trip_number' => 'TRP-'.strtoupper(Str::random(8)),
            'customer_id' => User::factory(),
            'rider_id' => null,
            'vehicle_id' => null,
            'vehicle_type_id' => VehicleType::factory(),
            'zone_id' => Zone::factory(),
            'type' => 'ride',
            'status' => 'completed',
            'pickup_location' => Point::makeGeodetic(0.3476, 32.5825),
            'pickup_address' => fake()->streetAddress(),
            'dropoff_location' => Point::makeGeodetic(0.3136, 32.5811),
            'dropoff_address' => fake()->streetAddress(),
            'requested_at' => $requestedAt,
            'accepted_at' => $requestedAt,
            'arrived_at' => $requestedAt,
            'started_at' => $requestedAt,
            'completed_at' => $requestedAt,
            'distance_km' => fake()->randomFloat(2, 1, 20),
            'duration_minutes' => fake()->numberBetween(5, 60),
            'estimated_fare' => 5000,
            'final_fare' => 5000,
            'currency_code' => 'UGX',
            'promo_code_id' => null,
            'discount_amount' => 0,
            'payment_method' => 'cash',
            'payment_status' => 'paid',
        ];
    }

    public function delivery(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'delivery',
        ]);
    }

    public function rideShare(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => 'ride_share',
        ]);
    }
}
