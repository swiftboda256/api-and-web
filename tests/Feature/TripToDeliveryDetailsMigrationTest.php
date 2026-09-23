<?php

use App\Models\DeliveryDetails;
use App\Models\DeliveryStop;
use App\Models\Trip;

test('backfills an existing delivery_details row with the trip\'s customer, pickup/dropoff and fare fields', function () {
    $trip = Trip::factory()->create([
        'type' => 'delivery',
        'status' => 'completed',
        'distance_km' => 3.2,
        'duration_minutes' => 12,
        'estimated_fare' => 3500,
        'final_fare' => 3500,
        'currency_code' => 'UGX',
        'payment_method' => 'mobile_money',
        'payment_status' => 'paid',
    ]);

    $delivery = DeliveryDetails::factory()->for($trip)->create();

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $delivery->refresh();

    expect($delivery->sender_id)->toBe($trip->customer_id)
        ->and($delivery->status)->toBe('dropped_off')
        ->and($delivery->pickup_location->getLatitude())->toBe($trip->pickup_location->getLatitude())
        ->and($delivery->dropoff_location->getLatitude())->toBe($trip->dropoff_location->getLatitude())
        ->and((float) $delivery->distance_km)->toBe(3.2)
        ->and($delivery->duration_minutes)->toBe(12)
        ->and((float) $delivery->estimated_fare)->toBe(3500.0)
        ->and((float) $delivery->final_fare)->toBe(3500.0)
        ->and($delivery->currency_code)->toBe('UGX')
        ->and($delivery->payment_method)->toBe('mobile_money')
        ->and($delivery->payment_status)->toBe('paid');
});

test('backfills exactly one pickup and one dropoff delivery_stops row', function () {
    $trip = Trip::factory()->create(['type' => 'delivery', 'status' => 'completed']);
    $delivery = DeliveryDetails::factory()->for($trip)->create();

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $stops = DeliveryStop::query()->where('delivery_details_id', $delivery->id)->orderBy('sequence')->get();

    expect($stops)->toHaveCount(2)
        ->and($stops[0]->stop_type)->toBe('pickup')
        ->and($stops[0]->sequence)->toBe(1)
        ->and($stops[1]->stop_type)->toBe('dropoff')
        ->and($stops[1]->sequence)->toBe(2);
});

test('maps every trip status to the correct delivery status', function (string $tripStatus, string $expectedDeliveryStatus) {
    $trip = Trip::factory()->create(['type' => 'delivery', 'status' => $tripStatus]);
    $delivery = DeliveryDetails::factory()->for($trip)->create();

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    expect($delivery->refresh()->status)->toBe($expectedDeliveryStatus);
})->with([
    ['requested', 'requested'],
    ['searching', 'requested'],
    ['accepted', 'matched'],
    ['arrived', 'arrived_pickup'],
    ['in_progress', 'picked_up'],
    ['completed', 'dropped_off'],
    ['cancelled', 'cancelled'],
]);

test('is idempotent -- running the backfill twice does not duplicate delivery_stops or re-touch the row', function () {
    $trip = Trip::factory()->create(['type' => 'delivery', 'status' => 'completed']);
    $delivery = DeliveryDetails::factory()->for($trip)->create();

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);
    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    expect(DeliveryStop::query()->where('delivery_details_id', $delivery->id)->count())->toBe(2);
});

test('does not touch a delivery already backfilled (sender_id already set)', function () {
    $trip = Trip::factory()->create(['type' => 'delivery', 'status' => 'completed']);
    $delivery = DeliveryDetails::factory()->for($trip)->create(['sender_id' => $trip->customer_id, 'status' => 'dropped_off']);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    expect(DeliveryStop::query()->where('delivery_details_id', $delivery->id)->count())->toBe(0);
});

test('reports a delivery trip with no delivery_details row instead of crashing', function () {
    Trip::factory()->create(['type' => 'delivery', 'status' => 'completed']);

    $this->artisan('trips:backfill-passenger-model')
        ->expectsOutputToContain('1 delivery trip(s) have no delivery_details row')
        ->assertExitCode(0);
});

test('dry-run reports counts without writing anything', function () {
    $trips = Trip::factory()->count(2)->create(['type' => 'delivery', 'status' => 'completed']);
    $trips->each(fn (Trip $trip) => DeliveryDetails::factory()->for($trip)->create());

    $this->artisan('trips:backfill-passenger-model', ['--dry-run' => true])
        ->expectsOutputToContain('and 2 delivery trip(s)')
        ->assertExitCode(0);

    expect(DeliveryDetails::query()->whereNotNull('sender_id')->count())->toBe(0)
        ->and(DeliveryStop::query()->count())->toBe(0);
});
