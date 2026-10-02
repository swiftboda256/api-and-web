<?php

use App\Models\DeliveryDetails;
use App\Models\PricingRule;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Wallet;
use App\Models\Zone;
use App\Services\Push\FcmGateway;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

/**
 * Cash settlement on shared trips: each passenger/delivery pays their own fare, so the
 * driver can confirm one at a time (passengers/{id}/settle-cash, deliveries/{id}/settle-cash)
 * as well as everything outstanding at once (settle-cash).
 *
 * Each fare is 3000 with a rider earning of 2730, i.e. 270 commission taken from the
 * driver's wallet per cash payment settled.
 */
beforeEach(function () {
    $this->app->instance(FcmGateway::class, Mockery::spy(FcmGateway::class));

    // The platform account commission transactions are credited to.
    Role::query()->create(['name' => 'system', 'guard_name' => 'web']);
    User::factory()->create()->assignRole('system');

    $this->zone = Zone::factory()->create();
    $this->vehicleType = VehicleType::factory()->create(['capacity' => 4]);
    PricingRule::factory()->create(['zone_id' => $this->zone->id, 'vehicle_type_id' => $this->vehicleType->id]);

    $this->driver = User::factory()->create();
    RiderProfile::factory()->create(['user_id' => $this->driver->id, 'availability_status' => 'on_trip']);
    $this->wallet = Wallet::query()->create(['user_id' => $this->driver->id, 'balance' => 10000, 'currency_code' => 'UGX', 'status' => 'active']);

    Sanctum::actingAs($this->driver);
});

function sharedTrip(string $type): Trip
{
    return Trip::factory()->create([
        'type' => $type,
        'zone_id' => test()->zone->id,
        'vehicle_type_id' => test()->vehicleType->id,
        'rider_id' => test()->driver->id,
        'status' => 'in_progress',
        'completed_at' => null,
    ]);
}

function droppedOffCashPassenger(Trip $trip): TripPassenger
{
    $passenger = TripPassenger::query()->create([
        'trip_id' => $trip->id,
        'customer_id' => User::factory()->create()->id,
        'seats_requested' => 1,
        'status' => 'dropped_off',
        'requested_at' => now(),
        'dropped_off_at' => now(),
    ]);

    TripFareBreakdown::query()->create([
        'trip_id' => $trip->id,
        'passenger_id' => $passenger->id,
        'customer_id' => $passenger->customer_id,
        'base_fare' => 1000,
        'estimated_fare' => 3000,
        'final_fare' => 3000,
        'commission_rate' => 9,
        'commission_amount' => 270,
        'rider_earning' => 2730,
        'total' => 3000,
        'currency_code' => 'UGX',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
    ]);

    return $passenger;
}

function droppedOffCashDelivery(Trip $trip): DeliveryDetails
{
    return DeliveryDetails::factory()->create([
        'trip_id' => $trip->id,
        'sender_id' => User::factory()->create()->id,
        'status' => 'dropped_off',
        'final_fare' => 3000,
        'currency_code' => 'UGX',
        'payment_method' => 'cash',
        'payment_status' => 'pending',
    ]);
}

test('settling one ride-share passenger leaves the others unpaid', function () {
    $trip = sharedTrip('ride_share');
    $paid = droppedOffCashPassenger($trip);
    $unpaid = droppedOffCashPassenger($trip);

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$paid->id}/settle-cash")
        ->assertOk()
        ->assertJsonPath('data.id', $paid->id)
        ->assertJsonPath('data.payment_status', 'paid');

    expect($paid->fareBreakdown->fresh()->payment_status)->toBe('paid')
        ->and($unpaid->fareBreakdown->fresh()->payment_status)->toBe('pending')
        ->and((float) $this->wallet->fresh()->balance)->toBe(9730.0);
});

test('a passenger already settled cannot be settled again', function () {
    $trip = sharedTrip('ride_share');
    $passenger = droppedOffCashPassenger($trip);

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$passenger->id}/settle-cash")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$passenger->id}/settle-cash")
        ->assertUnprocessable()
        ->assertJsonPath('errors.payment_status.0', 'This passenger has no cash payment waiting to be settled.');

    expect((float) $this->wallet->fresh()->balance)->toBe(9730.0);
});

test('a passenger not yet dropped off cannot be settled', function () {
    $trip = sharedTrip('ride_share');
    $passenger = droppedOffCashPassenger($trip);
    $passenger->update(['status' => 'picked_up']);

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$passenger->id}/settle-cash")->assertUnprocessable();

    expect($passenger->fareBreakdown->fresh()->payment_status)->toBe('pending');
});

test('a passenger from another trip is not found', function () {
    $trip = sharedTrip('ride_share');
    $otherTrip = sharedTrip('ride_share');
    $passenger = droppedOffCashPassenger($otherTrip);

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$passenger->id}/settle-cash")->assertNotFound();
});

test('the passenger endpoint rejects delivery trips', function () {
    $trip = sharedTrip('delivery_share');

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/1/settle-cash")->assertUnprocessable();
});

test('settling one pooled delivery leaves the others unpaid', function () {
    $trip = sharedTrip('delivery_share');
    $paid = droppedOffCashDelivery($trip);
    $unpaid = droppedOffCashDelivery($trip);

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/deliveries/{$paid->id}/settle-cash")
        ->assertOk()
        ->assertJsonPath('data.id', $paid->id)
        ->assertJsonPath('data.payment_status', 'paid');

    expect($paid->fresh()->payment_status)->toBe('paid')
        ->and($unpaid->fresh()->payment_status)->toBe('pending')
        ->and((float) $this->wallet->fresh()->balance)->toBe(9730.0);
});

test('the trip-wide settle-cash still settles everything outstanding', function () {
    $trip = sharedTrip('ride_share');
    $first = droppedOffCashPassenger($trip);
    $second = droppedOffCashPassenger($trip);

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$first->id}/settle-cash")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/settle-cash")->assertOk();

    expect($second->fareBreakdown->fresh()->payment_status)->toBe('paid')
        ->and((float) $this->wallet->fresh()->balance)->toBe(9460.0);
});
