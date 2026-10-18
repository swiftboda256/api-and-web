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
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

/**
 * Customers open and cancel their own booking by its booking id: GET trips/{id}?type=...,
 * PATCH cancel-ride/{trip_passenger_id} for rides, PATCH
 * cancel-delivery/{delivery_details_id} for deliveries.
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

    $this->customer = User::factory()->customer()->create();
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

test('cancelling a ride by booking id cancels the customer\'s passenger booking and the trip', function () {
    $booking = book(['type' => 'ride']);
    $reason = TripCancellationReason::query()->create(['label' => 'Changed my mind', 'applies_to' => 'customer', 'is_active' => true]);

    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['id']}", ['cancellation_reason_id' => $reason->id])
        ->assertOk()
        ->assertJsonPath('data.id', $booking['id'])
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.trip_status', 'cancelled')
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

test('cancelling a delivery by booking id cancels the customer\'s delivery booking', function () {
    $booking = book([
        'type' => 'delivery',
        'recipient_name' => 'Jane Namukasa',
        'recipient_phone' => '+256700123456',
    ]);

    $this->patchJson("/api/v1/user-app/trips/cancel-delivery/{$booking['id']}")
        ->assertOk()
        ->assertJsonPath('data.id', $booking['id'])
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.trip_status', 'cancelled')
        ->assertJsonPath('data.recipient_name', 'Jane Namukasa')
        ->assertJsonPath('data.pickup.latitude', 0.34)
        ->assertJsonPath('data.dropoff.latitude', 0.32);

    $delivery = DeliveryDetails::query()->findOrFail($booking['id']);

    expect($delivery->status)->toBe('cancelled')
        ->and($delivery->stops)->toHaveCount(2)
        ->and($delivery->stops->pluck('sequence')->filter()->all())->toBe([])
        ->and($delivery->trip->deliveryStops)->toHaveCount(0);
});

test('a customer cannot cancel someone else\'s booking', function () {
    $ride = book(['type' => 'ride']);
    $delivery = book(['type' => 'delivery', 'recipient_name' => 'Jane Namukasa', 'recipient_phone' => '+256700123456']);

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$ride['id']}")->assertNotFound();
    $this->patchJson("/api/v1/user-app/trips/cancel-delivery/{$delivery['id']}")->assertNotFound();

    expect(TripPassenger::query()->findOrFail($ride['id'])->status)->toBe('requested')
        ->and(DeliveryDetails::query()->findOrFail($delivery['id'])->status)->toBe('requested');
});

test('each cancel route only takes its own kind of booking', function () {
    // Number deliveries far from rides so neither id exists in the other table.
    DB::statement("SELECT setval(pg_get_serial_sequence('delivery_details', 'id'), 2000)");
    $ride = book(['type' => 'ride']);
    $delivery = book(['type' => 'delivery', 'recipient_name' => 'Jane Namukasa', 'recipient_phone' => '+256700123456']);

    $this->patchJson("/api/v1/user-app/trips/cancel-delivery/{$ride['id']}")->assertNotFound();
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$delivery['id']}")->assertNotFound();

    expect(TripPassenger::query()->findOrFail($ride['id'])->status)->toBe('requested')
        ->and(DeliveryDetails::query()->findOrFail($delivery['id'])->status)->toBe('requested');
});

test('cancel no longer takes the trip id', function () {
    // Push the trip ids ahead so a booking's trip_id never equals any booking id.
    DB::statement("SELECT setval(pg_get_serial_sequence('trips', 'id'), 1000)");
    $booking = book(['type' => 'ride']);

    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['trip_id']}")->assertNotFound();
    expect(TripPassenger::query()->findOrFail($booking['id'])->status)->toBe('requested');
});

test('bookings show the trip\'s own status alongside their own', function () {
    $booking = book(['type' => 'ride']);

    expect($booking['status'])->toBe('requested')
        ->and($booking['trip_status'])->toBe('searching');
});

test('cancelling an already-cancelled booking is rejected', function () {
    $booking = book(['type' => 'ride']);

    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['id']}")->assertOk();
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$booking['id']}")->assertUnprocessable();
});

test('the type parameter picks a ride or a delivery booking that share an id', function () {
    // The two tables count independently, so the same id can exist in both.
    DB::statement("SELECT setval(pg_get_serial_sequence('trip_passengers', 'id'), 5000)");
    DB::statement("SELECT setval(pg_get_serial_sequence('delivery_details', 'id'), 5000)");
    $ride = book(['type' => 'ride']);
    $delivery = book(['type' => 'delivery', 'recipient_name' => 'Jane Namukasa', 'recipient_phone' => '+256700123456']);

    expect($ride['id'])->toBe($delivery['id']);

    $this->getJson("/api/v1/user-app/trips/{$ride['id']}?type=ride")->assertOk()->assertJsonPath('data.type', 'ride');
    $this->getJson("/api/v1/user-app/trips/{$delivery['id']}?type=delivery")->assertOk()->assertJsonPath('data.type', 'delivery');

    // Without it, the ride is tried first -- the clash the parameter resolves.
    $this->getJson("/api/v1/user-app/trips/{$delivery['id']}")->assertOk()->assertJsonPath('data.type', 'ride');
});

test('the type parameter only looks in the matching kind of booking', function () {
    DB::statement("SELECT setval(pg_get_serial_sequence('delivery_details', 'id'), 2000)");
    $ride = book(['type' => 'ride']);
    $delivery = book(['type' => 'delivery', 'recipient_name' => 'Jane Namukasa', 'recipient_phone' => '+256700123456']);

    $this->getJson("/api/v1/user-app/trips/{$ride['id']}?type=delivery")->assertNotFound();
    $this->getJson("/api/v1/user-app/trips/{$delivery['id']}?type=ride_share")->assertNotFound();
    $this->getJson("/api/v1/user-app/trips/{$ride['id']}?type=bus")->assertUnprocessable()->assertJsonValidationErrors('type');

    Sanctum::actingAs(User::factory()->customer()->create());
    $this->getJson("/api/v1/user-app/trips/{$ride['id']}?type=ride")->assertNotFound();
});
