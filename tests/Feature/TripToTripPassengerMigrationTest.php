<?php

use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\User;

test('backfills a solo ride trip into a trip_passengers row with matching fields', function () {
    $trip = Trip::factory()->create([
        'type' => 'ride',
        'status' => 'completed',
        'distance_km' => 8.5,
        'duration_minutes' => 22,
        'estimated_fare' => 6000,
        'final_fare' => 6200,
        'currency_code' => 'UGX',
        'payment_method' => 'cash',
        'payment_status' => 'paid',
    ]);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    expect(TripPassenger::query()->where('trip_id', $trip->id)->count())->toBe(1);

    $passenger = TripPassenger::query()->where('trip_id', $trip->id)->firstOrFail();
    $fareBreakdown = $passenger->fareBreakdown;

    expect($passenger->customer_id)->toBe($trip->customer_id)
        ->and($passenger->seats_requested)->toBe(1)
        ->and($passenger->status)->toBe('dropped_off')
        ->and((float) $passenger->distance_km)->toBe(8.5)
        ->and($passenger->duration_minutes)->toBe(22)
        ->and($fareBreakdown)->not->toBeNull()
        ->and((float) $fareBreakdown->estimated_fare)->toBe(6000.0)
        ->and((float) $fareBreakdown->final_fare)->toBe(6200.0)
        ->and($fareBreakdown->currency_code)->toBe('UGX')
        ->and($fareBreakdown->payment_method)->toBe('cash')
        ->and($fareBreakdown->payment_status)->toBe('paid');
});

test('backfills exactly one pickup and one dropoff stop per solo ride trip', function () {
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'completed']);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $passenger = TripPassenger::query()->where('trip_id', $trip->id)->firstOrFail();
    $stops = TripStop::query()->where('trip_passenger_id', $passenger->id)->orderBy('sequence')->get();

    expect($stops)->toHaveCount(2)
        ->and($stops[0]->stop_type)->toBe('pickup')
        ->and($stops[0]->seats_delta)->toBe(1)
        ->and($stops[0]->sequence)->toBe(1)
        ->and($stops[1]->stop_type)->toBe('dropoff')
        ->and($stops[1]->seats_delta)->toBe(-1)
        ->and($stops[1]->sequence)->toBe(2);
});

test('maps every trip status to the correct passenger status', function (string $tripStatus, string $expectedPassengerStatus) {
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => $tripStatus]);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $passenger = TripPassenger::query()->where('trip_id', $trip->id)->firstOrFail();

    expect($passenger->status)->toBe($expectedPassengerStatus);
})->with([
    ['requested', 'requested'],
    ['searching', 'requested'],
    ['accepted', 'matched'],
    ['arrived', 'arrived_pickup'],
    ['in_progress', 'picked_up'],
    ['completed', 'dropped_off'],
    ['cancelled', 'cancelled'],
]);

test('marks the dropoff stop as arrived only for a completed trip', function () {
    $completedTrip = Trip::factory()->create(['type' => 'ride', 'status' => 'completed', 'completed_at' => now()]);
    $inProgressTrip = Trip::factory()->create(['type' => 'ride', 'status' => 'in_progress', 'completed_at' => null]);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $completedPassenger = TripPassenger::query()->where('trip_id', $completedTrip->id)->firstOrFail();
    $inProgressPassenger = TripPassenger::query()->where('trip_id', $inProgressTrip->id)->firstOrFail();

    $completedDropoff = TripStop::query()->where('trip_passenger_id', $completedPassenger->id)->where('stop_type', 'dropoff')->firstOrFail();
    $inProgressDropoff = TripStop::query()->where('trip_passenger_id', $inProgressPassenger->id)->where('stop_type', 'dropoff')->firstOrFail();

    expect($completedDropoff->arrived_at)->not->toBeNull()
        ->and($inProgressDropoff->arrived_at)->toBeNull();
});

test('is idempotent -- running the backfill twice does not duplicate rows', function () {
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'completed']);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);
    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    expect(TripPassenger::query()->where('trip_id', $trip->id)->count())->toBe(1)
        ->and(TripStop::query()->whereHas('tripPassenger', fn ($query) => $query->where('trip_id', $trip->id))->count())->toBe(2);
});

test('does not touch a ride_share trip that already has trip_passengers', function () {
    $trip = Trip::factory()->rideShare()->create(['status' => 'in_progress']);

    foreach (range(1, 2) as $i) {
        TripPassenger::query()->create([
            'trip_id' => $trip->id,
            'customer_id' => User::factory()->create()->id,
            'seats_requested' => 1,
            'status' => 'picked_up',
            'estimated_fare' => 4000,
            'currency_code' => 'UGX',
            'requested_at' => now(),
        ]);
    }

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    expect(TripPassenger::query()->where('trip_id', $trip->id)->count())->toBe(2);
});

test('dry-run reports counts without writing anything', function () {
    Trip::factory()->count(3)->create(['type' => 'ride', 'status' => 'completed']);

    $this->artisan('trips:backfill-passenger-model', ['--dry-run' => true])
        ->expectsOutputToContain('Would backfill 3 ride trip(s)')
        ->assertExitCode(0);

    expect(TripPassenger::query()->count())->toBe(0)
        ->and(TripStop::query()->count())->toBe(0);
});
