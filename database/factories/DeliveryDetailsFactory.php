<?php

namespace Database\Factories;

use App\Models\DeliveryDetails;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds a delivery_details row in its pre-unification shape: only the recipient/package
 * fields set, everything added by the customer-fields migration (sender_id, status,
 * pickup/dropoff, fare, payment) left unset -- what an already-existing delivery looks
 * like before the backfill runs.
 *
 * @extends Factory<DeliveryDetails>
 */
class DeliveryDetailsFactory extends Factory
{
    protected $model = DeliveryDetails::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory()->delivery(),
            'recipient_name' => fake()->name(),
            'recipient_phone' => fake()->e164PhoneNumber(),
            'package_description' => fake()->sentence(3),
            'package_size' => 'small',
            'package_weight_kg' => fake()->randomFloat(2, 0.5, 5),
            'requires_signature' => false,
        ];
    }
}
