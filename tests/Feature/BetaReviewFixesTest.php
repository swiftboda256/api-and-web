<?php

use App\Models\PricingRule;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Push\ApnsVoipGateway;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Kreait\Firebase\Contract\Messaging;
use Laravel\Sanctum\Sanctum;

/**
 * Fixes from the beta review: bookings of deleted trips, cancelling a trip that's under
 * way, client times sent in UTC, and trips stuck searching from before the new dispatch.
 */
beforeEach(function () {
    config(['app.timezone' => 'Africa/Kampala']);
    date_default_timezone_set('Africa/Kampala');

    $this->push = new class(Mockery::mock(Messaging::class), new ApnsVoipGateway) extends FcmGateway
    {
        /** @var list<array{0: int|null, 1: PushMessage}> */
        public array $sent = [];

        public function sendToUser(?User $user, PushMessage $message): void
        {
            $this->sent[] = [$user?->id, $message];
        }
    };
    $this->app->instance(FcmGateway::class, $this->push);

    $this->zone = Zone::factory()->create(['boundary' => Polygon::make([LineString::make([
        Point::makeGeodetic(0.0, 32.3), Point::makeGeodetic(0.0, 32.9), Point::makeGeodetic(0.7, 32.9),
        Point::makeGeodetic(0.7, 32.3), Point::makeGeodetic(0.0, 32.3),
    ])])]);
    $this->vehicleType = VehicleType::factory()->create(['capacity' => 1]);
    PricingRule::factory()->create(['zone_id' => $this->zone->id, 'vehicle_type_id' => $this->vehicleType->id]);
    $this->customer = User::factory()->customer()->create();
});

function reviewRider($test): User
{
    $rider = User::factory()->rider()->create();
    $profile = RiderProfile::factory()->create(['user_id' => $rider->id, 'availability_status' => 'online', 'current_location' => Point::makeGeodetic(0.341, 32.5825)]);
    Vehicle::factory()->create(['rider_profile_id' => $profile->id, 'vehicle_type_id' => $test->vehicleType->id]);

    return $rider;
}

function reviewBookRide($test): TripPassenger
{
    Sanctum::actingAs($test->customer);

    $id = $test->postJson('/api/v1/user-app/trips/order-ride', [
        'type' => 'ride', 'vehicle_type_id' => $test->vehicleType->id,
        'pickup_latitude' => 0.34, 'pickup_longitude' => 32.5825,
        'dropoff_latitude' => 0.32, 'dropoff_longitude' => 32.5825,
        'distance_km' => 2.2, 'payment_method' => 'cash',
    ])->assertCreated()->json('data.id');

    return TripPassenger::query()->findOrFail($id);
}

function reviewPushes($test, User $user, string $type): array
{
    return collect($test->push->sent)->filter(fn ($p) => $p[0] === $user->id && $p[1]->type === $type)->values()->all();
}

// --- Bookings of deleted trips -------------------------------------------------------

test('deleting a trip deletes its bookings, and restoring it brings them back', function () {
    $passenger = reviewBookRide($this);

    $passenger->trip->delete();
    expect(TripPassenger::query()->find($passenger->id))->toBeNull();

    Trip::withTrashed()->findOrFail($passenger->trip_id)->restore();
    expect(TripPassenger::query()->find($passenger->id))->not->toBeNull();
});

test('a booking whose trip was deleted no longer breaks the trip list', function () {
    $kept = reviewBookRide($this);
    $orphaned = reviewBookRide($this);

    // How beta's orphans were made: the trip deleted without its bookings.
    Trip::withoutEvents(fn () => $orphaned->trip->delete());

    Sanctum::actingAs($this->customer);
    $this->getJson('/api/v1/user-app/trips')
        ->assertOk()
        ->assertJsonCount(1, 'data.trips')
        ->assertJsonPath('data.trips.0.id', $kept->id);
    $this->getJson("/api/v1/user-app/trips/{$orphaned->id}?type=ride")->assertNotFound();
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$orphaned->id}")->assertNotFound();
});

// --- Cancelling a trip under way -----------------------------------------------------

test('a passenger cannot cancel once they have been picked up', function () {
    $rider = reviewRider($this);
    $passenger = reviewBookRide($this);

    Sanctum::actingAs($rider);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/start")->assertOk();

    Sanctum::actingAs($this->customer);
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$passenger->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'You can\'t cancel a ride that is already under way.');

    expect($passenger->fresh()->status)->toBe('picked_up');
});

test('a rider cancelling before pickup puts the booking back to waiting for another rider', function () {
    $first = reviewRider($this);
    $second = reviewRider($this);
    $passenger = reviewBookRide($this);

    Sanctum::actingAs($first);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/arrive")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/cancel")->assertOk();

    expect($passenger->fresh()->status)->toBe('requested')
        ->and($passenger->fresh()->matched_at)->toBeNull()
        ->and($passenger->trip->fresh()->status)->toBe('searching')
        ->and(TripStop::query()->where('trip_passenger_id', $passenger->id)->where('stop_type', 'pickup')->value('arrived_at'))->toBeNull()
        ->and(reviewPushes($this, $this->customer, 'trip_rider_cancelled'))->toHaveCount(1);

    // Another rider takes it, and gets the passenger.
    Sanctum::actingAs($second);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();

    expect($passenger->fresh()->status)->toBe('matched');
});

test('a rider cancelling a trip under way cancels it and the booking, without searching again', function () {
    $rider = reviewRider($this);
    $passenger = reviewBookRide($this);

    Sanctum::actingAs($rider);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/start")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/cancel")->assertOk();

    expect($passenger->trip->fresh()->status)->toBe('cancelled')
        ->and($passenger->fresh()->status)->toBe('cancelled')
        ->and($rider->riderProfile->fresh()->availability_status)->toBe('online')
        ->and(reviewPushes($this, $this->customer, 'trip_cancelled'))->toHaveCount(1)
        ->and(reviewPushes($this, $this->customer, 'trip_rider_cancelled'))->toBe([]);
});

test('a trip under way closes when the customer cancels the last booking still to be picked up', function () {
    $rider = reviewRider($this);
    $trip = Trip::factory()->rideShare()->create(['zone_id' => $this->zone->id, 'vehicle_type_id' => $this->vehicleType->id, 'rider_id' => $rider->id, 'status' => 'in_progress', 'completed_at' => null]);
    $rider->riderProfile->update(['availability_status' => 'on_trip']);
    TripPassenger::query()->create(['trip_id' => $trip->id, 'customer_id' => User::factory()->create()->id, 'seats_requested' => 1, 'status' => 'dropped_off', 'requested_at' => now()]);
    $waiting = TripPassenger::query()->create(['trip_id' => $trip->id, 'customer_id' => $this->customer->id, 'seats_requested' => 1, 'status' => 'matched', 'requested_at' => now(), 'matched_at' => now()]);

    Sanctum::actingAs($this->customer);
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$waiting->id}")->assertOk();

    expect($trip->fresh()->status)->toBe('completed')
        ->and($rider->riderProfile->fresh()->availability_status)->toBe('online');
});

// --- Times sent in UTC ---------------------------------------------------------------

test('a posted ride departure sent in UTC is saved as the right time, and its last seat can be booked', function () {
    $driver = reviewRider($this);
    Sanctum::actingAs($driver);

    $departsUtc = now()->utc()->addHours(2)->startOfSecond();
    $tripId = $this->postJson('/api/v1/rider-app/rides/posted', [
        'origin_latitude' => 0.3476, 'origin_longitude' => 32.5825,
        'destination_latitude' => 0.0512, 'destination_longitude' => 32.4637,
        'departs_at' => $departsUtc->format('Y-m-d\TH:i:s\Z'),
        'seat_fare' => 7000, 'distance_km' => 38,
    ])->assertCreated()->json('data.id');

    expect(Trip::query()->findOrFail($tripId)->departs_at->equalTo($departsUtc))->toBeTrue();

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/trips/posted/{$tripId}/book", ['payment_method' => 'cash'])->assertCreated();
});

test('booking a posted ride that has departed says so', function () {
    $trip = Trip::factory()->create([
        'type' => 'posted_ride', 'status' => 'open', 'zone_id' => $this->zone->id, 'vehicle_type_id' => $this->vehicleType->id,
        'rider_id' => reviewRider($this)->id, 'available_seats' => 1, 'seat_fare' => 7000, 'departs_at' => now()->subMinute(),
        'origin_location' => Point::makeGeodetic(0.3476, 32.5825), 'destination_location' => Point::makeGeodetic(0.0512, 32.4637),
        'completed_at' => null,
    ]);

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/trips/posted/{$trip->id}/book", ['payment_method' => 'cash'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.trip.0', 'This ride has already departed.');
});

test('a scheduled pickup time sent in UTC is saved as the right time', function () {
    Sanctum::actingAs($this->customer);
    $atUtc = now()->utc()->addDay()->startOfSecond();

    $id = $this->postJson('/api/v1/user-app/trips/schedule', [
        'requested_at' => $atUtc->format('Y-m-d\TH:i:s\Z'),
        'type' => 'ride', 'vehicle_type_id' => $this->vehicleType->id,
        'pickup_latitude' => 0.34, 'pickup_longitude' => 32.5825,
        'dropoff_latitude' => 0.32, 'dropoff_longitude' => 32.5825,
        'distance_km' => 2.2, 'payment_method' => 'cash',
    ])->assertCreated()->json('data.id');

    expect(TripPassenger::query()->findOrFail($id)->trip->requested_at->equalTo($atUtc))->toBeTrue();
});

// --- Trips stuck searching -----------------------------------------------------------

test('the backfill cancels trips stuck searching from before the timeout, even if already backfilled', function () {
    // Already on the booking model (as on beta), searching for days with no search start.
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'searching', 'requested_at' => now()->subDays(4), 'completed_at' => null]);
    $passenger = TripPassenger::query()->create(['trip_id' => $trip->id, 'customer_id' => $this->customer->id, 'seats_requested' => 1, 'status' => 'requested', 'requested_at' => $trip->requested_at]);

    $this->artisan('trips:backfill-passenger-model')
        ->expectsOutputToContain('Cancelled 1 trip(s) left searching past the search timeout.')
        ->assertExitCode(0);

    expect($trip->fresh()->status)->toBe('cancelled')
        ->and($passenger->fresh()->status)->toBe('cancelled');
});

test('a search with no booking still waiting rings nobody, and ends at its timeout', function () {
    $rider = reviewRider($this);
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'searching', 'zone_id' => $this->zone->id, 'vehicle_type_id' => $this->vehicleType->id, 'search_started_at' => now(), 'completed_at' => null]);
    $passenger = TripPassenger::query()->create(['trip_id' => $trip->id, 'customer_id' => $this->customer->id, 'seats_requested' => 1, 'status' => 'cancelled', 'requested_at' => now()]);
    TripStop::query()->create(['trip_id' => $trip->id, 'trip_passenger_id' => $passenger->id, 'stop_type' => 'pickup', 'seats_delta' => 1, 'sequence' => null, 'location' => Point::makeGeodetic(0.34, 32.5825)]);

    $this->artisan('trips:process-dispatch')->assertSuccessful();
    expect(reviewPushes($this, $rider, 'ride_offer'))->toBe([]);

    $this->travel(6)->minutes();
    $this->artisan('trips:process-dispatch')->assertSuccessful();
    expect($trip->fresh()->status)->toBe('cancelled');
});
