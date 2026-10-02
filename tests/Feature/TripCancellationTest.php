<?php

use App\Models\DeliveryDetails;
use App\Models\PricingRule;
use App\Models\TripCancellationReason;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Push\FcmGateway;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Laravel\Sanctum\Sanctum;

/**
 * PATCH user-app/trips/cancel-ride/{trip}: one cancel endpoint for every trip type, keyed
 * by the vehicle trip_id; the trip's type decides whether the customer's passenger or
 * delivery booking is cancelled.
 */
beforeEach(function () {
    $this->app->instance(FcmGateway::class, Mockery::spy(FcmGateway::class));

    $zone = Zone::factory()->create([
        'boundary' => Polygon::make([LineString::make([
            Point::makeGeodetic(0.0, 32.3),
            Point::makeGeodetic(0.0, 32.9),
            Point::makeGeodetic(0.7, 32.9),
            Point::makeGeodetic(0.7, 32.3),
            Point::makeGeodetic(0.0, 32.3),
        ])]),
    ]);
    $this->vehicleType = VehicleType::factory()->create(['capacity' => 1, 'max_cargo_weight_kg' => 20]);
    PricingRule::factory()->create(['zone_id' => $zone->id, 'vehicle_type_id' => $this->vehicleType->id]);

    $this->customer = User::factory()->create();
    Sanctum::actingAs($this->customer);
});

function book(array $extra): array
{
    return test()->postJson('/api/v1/user-app/trips/order-ride', [
        'vehicle_type_id' => test()->vehicleType->id,
        'pickup_latitude' => 0.34, 'pickup_longitude' => 32.5825,
        'dropoff_latitude' => 0.32, 'dropoff_longitude' => 32.5825,
        'distance_km' => 2.2,
        'payment_method' => 'cash',
        ...$extra,
    ])->assertCreated()->json('data');
}

test('cancelling a ride by trip id cancels the customer\'s passenger booking and the trip', function () {
    $booking = book(['type' => 'ride']);
    $reason = TripCancellationReason::query()->create(['label' => 'Changed my mind', 'applies_to' => 'customer', 'is_active' => true]);

    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['trip_id']}", ['cancellation_reason_id' => $reason->id])
        ->assertOk()
        ->assertJsonPath('data.id', $booking['id'])
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Changed my mind')
        ->assertJsonPath('data.customer.id', $this->customer->id)
        ->assertJsonPath('data.vehicle_type.id', $this->vehicleType->id)
        ->assertJsonPath('data.pickup.latitude', 0.34)
        ->assertJsonPath('data.dropoff.latitude', 0.32);

    $passenger = TripPassenger::query()->findOrFail($booking['id']);

    // Stops kept for history, but off the route.
    expect($passenger->status)->toBe('cancelled')
        ->and($passenger->trip->status)->toBe('cancelled')
        ->and($passenger->stops)->toHaveCount(2)
        ->and($passenger->stops->pluck('sequence')->filter()->all())->toBe([])
        ->and($passenger->trip->stops)->toHaveCount(0);
});

test('cancelling a delivery by trip id cancels the customer\'s delivery booking', function () {
    $booking = book([
        'type' => 'delivery',
        'recipient_name' => 'Jane Namukasa',
        'recipient_phone' => '+256700123456',
    ]);

    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['trip_id']}")
        ->assertOk()
        ->assertJsonPath('data.id', $booking['id'])
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.recipient_name', 'Jane Namukasa')
        ->assertJsonPath('data.pickup.latitude', 0.34)
        ->assertJsonPath('data.dropoff.latitude', 0.32);

    $delivery = DeliveryDetails::query()->findOrFail($booking['id']);

    expect($delivery->status)->toBe('cancelled')
        ->and($delivery->stops)->toHaveCount(2)
        ->and($delivery->stops->pluck('sequence')->filter()->all())->toBe([])
        ->and($delivery->trip->deliveryStops)->toHaveCount(0);
});

test('a customer cannot cancel a trip they have no booking on', function () {
    $booking = book(['type' => 'ride']);

    Sanctum::actingAs(User::factory()->create());
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['trip_id']}")->assertNotFound();

    expect(TripPassenger::query()->findOrFail($booking['id'])->status)->toBe('requested');
});

test('cancelling an already-cancelled booking is rejected', function () {
    $booking = book(['type' => 'ride']);

    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['trip_id']}")->assertOk();
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['trip_id']}")->assertUnprocessable();
});
