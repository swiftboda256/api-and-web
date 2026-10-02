<?php

use App\Models\PricingRule;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\User;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Push\FcmGateway;
use App\Services\Routing\Contracts\RoutingGateway;
use App\Services\Routing\RouteResult;
use App\Services\Trip\RideShareMatchingService;
use App\Support\Geo;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Laravel\Sanctum\Sanctum;

/**
 * Ride-share passengers must be accepted by the ongoing trip's driver before joining.
 * Routing is faked with straight-line distances (2 min/km); the ongoing trip drives due
 * south from the driver's position, and the joining passenger rides along that line.
 */
beforeEach(function () {
    $this->app->bind(RoutingGateway::class, fn () => new class implements RoutingGateway
    {
        public function computeRoute(array $waypoints): RouteResult
        {
            $legs = [];

            for ($i = 0; $i < count($waypoints) - 1; $i++) {
                $km = round(Geo::haversineKm($waypoints[$i], $waypoints[$i + 1]), 2);
                $legs[] = ['distance_km' => $km, 'duration_minutes' => (int) round($km * 2)];
            }

            return new RouteResult(
                array_sum(array_column($legs, 'distance_km')),
                array_sum(array_column($legs, 'duration_minutes')),
                null,
                $legs,
            );
        }
    });

    $this->push = Mockery::spy(FcmGateway::class);
    $this->app->instance(FcmGateway::class, $this->push);

    $this->zone = Zone::factory()->create([
        'boundary' => Polygon::make([LineString::make([
            Point::makeGeodetic(0.0, 32.3),
            Point::makeGeodetic(0.0, 32.9),
            Point::makeGeodetic(0.7, 32.9),
            Point::makeGeodetic(0.7, 32.3),
            Point::makeGeodetic(0.0, 32.3),
        ])]),
    ]);
    $this->vehicleType = VehicleType::factory()->create(['capacity' => 4]);
    PricingRule::factory()->create(['zone_id' => $this->zone->id, 'vehicle_type_id' => $this->vehicleType->id]);

    $this->ongoing = ongoingRideShareTrip($this->zone, $this->vehicleType, driverAt: Point::makeGeodetic(0.3476, 32.5825));
    $this->customer = User::factory()->create();
});

/**
 * An in-progress ride-share trip with one picked-up passenger heading due south.
 */
function ongoingRideShareTrip(Zone $zone, VehicleType $vehicleType, Point $driverAt): Trip
{
    $driver = User::factory()->create();
    RiderProfile::factory()->create([
        'user_id' => $driver->id,
        'availability_status' => 'on_trip',
        'current_location' => $driverAt,
    ]);

    $trip = Trip::factory()->rideShare()->create([
        'zone_id' => $zone->id,
        'vehicle_type_id' => $vehicleType->id,
        'rider_id' => $driver->id,
        'status' => 'in_progress',
        'available_seats' => 3,
        'passenger_count' => 1,
        'completed_at' => null,
    ]);

    $passenger = TripPassenger::query()->create([
        'trip_id' => $trip->id,
        'customer_id' => User::factory()->create()->id,
        'seats_requested' => 1,
        'status' => 'picked_up',
        'requested_at' => now(),
        'matched_at' => now(),
        'picked_up_at' => now(),
    ]);

    TripStop::query()->create([
        'trip_id' => $trip->id, 'trip_passenger_id' => $passenger->id, 'stop_type' => 'pickup',
        'seats_delta' => 1, 'sequence' => 1, 'location' => $driverAt, 'arrived_at' => now(),
    ]);
    TripStop::query()->create([
        'trip_id' => $trip->id, 'trip_passenger_id' => $passenger->id, 'stop_type' => 'dropoff',
        'seats_delta' => -1, 'sequence' => 2, 'location' => Point::makeGeodetic($driverAt->getLatitude() - 0.05, $driverAt->getLongitude()),
    ]);

    return $trip;
}

function orderRideShare($test): TripPassenger
{
    Sanctum::actingAs($test->customer);

    $response = $test->postJson('/api/v1/user-app/trips/order-ride', [
        'type' => 'ride_share',
        'vehicle_type_id' => $test->vehicleType->id,
        'pickup_latitude' => 0.3400,
        'pickup_longitude' => 32.5825,
        'pickup_address' => 'Pickup B',
        'dropoff_latitude' => 0.3200,
        'dropoff_longitude' => 32.5825,
        'dropoff_address' => 'Dropoff B',
        'distance_km' => 2.2,
        'payment_method' => 'cash',
    ])->assertCreated();

    return TripPassenger::query()->findOrFail($response->json('data.id'));
}

function driverOf(Trip $trip): User
{
    return User::query()->findOrFail($trip->rider_id);
}

test('booking offers the passenger to the ongoing trip as pending_approval and notifies the driver', function () {
    $passenger = orderRideShare($this);

    expect($passenger->status)->toBe('pending_approval')
        ->and($passenger->trip_id)->toBe($this->ongoing->id)
        ->and($passenger->request_expires_at)->not->toBeNull()
        ->and((int) abs($passenger->request_expires_at->diffInSeconds(now())))->toBeBetween(28, 31)
        ->and($passenger->detour_minutes)->not->toBeNull()
        ->and($passenger->fareBreakdown->payment_method)->toBe('cash');

    // Stops recorded but not on the route yet.
    expect($passenger->stops)->toHaveCount(2)
        ->and($passenger->stops->pluck('sequence')->filter()->all())->toBe([])
        ->and($this->ongoing->fresh()->stops)->toHaveCount(2);

    // Seats held, passenger count unchanged until accepted.
    $trip = $this->ongoing->fresh();
    expect($trip->available_seats)->toBe(2)
        ->and($trip->passenger_count)->toBe(1);

    $this->push->shouldHaveReceived('sendToTokens')
        ->withArgs(fn ($tokens, $title, $body, $data) => $title === 'New ride-share request'
            && $data['type'] === 'ride_share_join_request'
            && $data['trip_passenger_id'] === (string) $passenger->id)
        ->once();
});

test('the booking response hides the rider while pending approval', function () {
    Sanctum::actingAs($this->customer);

    $response = $this->postJson('/api/v1/user-app/trips/order-ride', [
        'type' => 'ride_share',
        'vehicle_type_id' => $this->vehicleType->id,
        'pickup_latitude' => 0.3400, 'pickup_longitude' => 32.5825,
        'dropoff_latitude' => 0.3200, 'dropoff_longitude' => 32.5825,
        'distance_km' => 2.2,
        'payment_method' => 'cash',
    ])->assertCreated();

    expect($response->json('data.status'))->toBe('pending_approval')
        ->and($response->json('data.rider'))->toBeNull()
        ->and($response->json('data.pickup.latitude'))->toEqualWithDelta(0.34, 0.0001);
});

test('the driver sees the pending passenger with detour and expiry on the ride detail', function () {
    $passenger = orderRideShare($this);
    Sanctum::actingAs(driverOf($this->ongoing));

    $response = $this->getJson("/api/v1/rider-app/rides/{$this->ongoing->id}")->assertOk();
    $pending = collect($response->json('data.ride_share.passengers'))->firstWhere('id', $passenger->id);

    expect($pending['status'])->toBe('pending_approval')
        ->and($pending['request_expires_at'])->not->toBeNull()
        ->and($pending['pickup']['address'])->toBe('Pickup B');
});

test('accepting adds the passenger to the route as matched', function () {
    $passenger = orderRideShare($this);
    Sanctum::actingAs(driverOf($this->ongoing));

    $this->patchJson("/api/v1/rider-app/rides/{$this->ongoing->id}/passengers/{$passenger->id}/accept")
        ->assertOk()
        ->assertJsonPath('data.status', 'matched');

    $passenger->refresh();
    $trip = $this->ongoing->fresh();
    $sequences = TripStop::query()->where('trip_id', $trip->id)->orderBy('sequence')->pluck('sequence')->all();

    expect($passenger->status)->toBe('matched')
        ->and($passenger->matched_at)->not->toBeNull()
        ->and($passenger->stops->pluck('sequence')->filter())->toHaveCount(2)
        ->and($sequences)->toBe([1, 2, 3, 4])
        ->and($trip->passenger_count)->toBe(2)
        ->and($trip->available_seats)->toBe(2);

    $this->push->shouldHaveReceived('sendToTokens')
        ->withArgs(fn ($tokens, $title) => $title === 'You have been matched')
        ->once();
});

test('declining with no other trip available moves the passenger onto a new ride-share trip', function () {
    $passenger = orderRideShare($this);
    Sanctum::actingAs(driverOf($this->ongoing));

    $this->patchJson("/api/v1/rider-app/rides/{$this->ongoing->id}/passengers/{$passenger->id}/decline")->assertOk();

    $passenger->refresh();
    $newTrip = $passenger->trip;

    expect($passenger->status)->toBe('requested')
        ->and($passenger->trip_id)->not->toBe($this->ongoing->id)
        ->and($passenger->declined_trip_ids)->toBe([$this->ongoing->id])
        ->and($passenger->request_expires_at)->toBeNull()
        ->and($newTrip->type)->toBe('ride_share')
        ->and($newTrip->status)->toBe('searching')
        ->and($passenger->stops->pluck('sequence')->all())->toBe([1, 2])
        ->and($passenger->stops->pluck('trip_id')->unique()->all())->toBe([$newTrip->id])
        ->and($passenger->fareBreakdown->trip_id)->toBe($newTrip->id)
        ->and($this->ongoing->fresh()->available_seats)->toBe(3);
});

test('declining re-offers to the next ongoing trip, never back to the one that declined', function () {
    $secondTrip = ongoingRideShareTrip($this->zone, $this->vehicleType, driverAt: Point::makeGeodetic(0.3470, 32.5825));
    $passenger = orderRideShare($this);
    $firstOffer = $passenger->trip_id;
    $otherTrip = $firstOffer === $this->ongoing->id ? $secondTrip : $this->ongoing;

    Sanctum::actingAs(driverOf(Trip::query()->findOrFail($firstOffer)));
    $this->patchJson("/api/v1/rider-app/rides/{$firstOffer}/passengers/{$passenger->id}/decline")->assertOk();

    $passenger->refresh();

    expect($passenger->status)->toBe('pending_approval')
        ->and($passenger->trip_id)->toBe($otherTrip->id)
        ->and($passenger->declined_trip_ids)->toBe([$firstOffer])
        ->and($passenger->stops->pluck('trip_id')->unique()->all())->toBe([$otherTrip->id])
        ->and($otherTrip->fresh()->available_seats)->toBe(2)
        ->and(Trip::query()->findOrFail($firstOffer)->available_seats)->toBe(3);

    // Second driver declines too: nothing left, so a brand-new trip.
    Sanctum::actingAs(driverOf($otherTrip));
    $this->patchJson("/api/v1/rider-app/rides/{$otherTrip->id}/passengers/{$passenger->id}/decline")->assertOk();

    expect($passenger->fresh()->status)->toBe('requested')
        ->and($passenger->fresh()->declined_trip_ids)->toBe([$firstOffer, $otherTrip->id]);
});

test('an unanswered request expires after the timeout and is re-offered', function () {
    $passenger = orderRideShare($this);

    $this->artisan('ride-share:expire-join-requests')->assertExitCode(0);
    expect($passenger->fresh()->status)->toBe('pending_approval');

    $this->travel(31)->seconds();
    $this->artisan('ride-share:expire-join-requests')
        ->expectsOutputToContain('Expired and re-offered 1')
        ->assertExitCode(0);

    expect($passenger->fresh()->status)->toBe('requested')
        ->and($passenger->fresh()->trip_id)->not->toBe($this->ongoing->id)
        ->and($this->ongoing->fresh()->available_seats)->toBe(3);
});

test('the driver cannot accept an expired request', function () {
    $passenger = orderRideShare($this);
    $this->travel(31)->seconds();

    Sanctum::actingAs(driverOf($this->ongoing));
    $this->patchJson("/api/v1/rider-app/rides/{$this->ongoing->id}/passengers/{$passenger->id}/accept")
        ->assertUnprocessable();

    expect($passenger->fresh()->status)->toBe('pending_approval');
});

test('a request whose trip has ended is released without waiting for the timeout', function () {
    $passenger = orderRideShare($this);
    $this->ongoing->update(['status' => 'completed']);

    $this->artisan('ride-share:expire-join-requests')->assertExitCode(0);

    expect($passenger->fresh()->status)->toBe('requested')
        ->and($passenger->fresh()->trip_id)->not->toBe($this->ongoing->id);
});

test('releasing twice does not return the held seats twice', function () {
    $passenger = orderRideShare($this);
    $matching = app(RideShareMatchingService::class);

    expect($matching->release($passenger))->toBeTrue()
        ->and($matching->release($passenger))->toBeFalse()
        ->and($this->ongoing->fresh()->available_seats)->toBe(3);
});

test('the customer can cancel while pending approval, releasing the seats but keeping the stops off the route', function () {
    $passenger = orderRideShare($this);

    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$passenger->trip_id}")
        ->assertOk()
        ->assertJsonPath('data.rider', null)
        ->assertJsonPath('data.pickup.address', 'Pickup B')
        ->assertJsonPath('data.dropoff.address', 'Dropoff B');

    expect($passenger->fresh()->status)->toBe('cancelled')
        ->and(TripStop::query()->where('trip_passenger_id', $passenger->id)->whereNull('sequence')->count())->toBe(2)
        ->and($this->ongoing->fresh()->stops)->toHaveCount(2)
        ->and($this->ongoing->fresh()->available_seats)->toBe(3)
        ->and($this->ongoing->fresh()->passenger_count)->toBe(1);
});

test('only a pending passenger on the driver\'s own trip can be accepted or declined', function () {
    $passenger = orderRideShare($this);
    $otherDriver = User::factory()->create();

    Sanctum::actingAs($otherDriver);
    $this->patchJson("/api/v1/rider-app/rides/{$this->ongoing->id}/passengers/{$passenger->id}/accept")->assertNotFound();

    Sanctum::actingAs(driverOf($this->ongoing));
    $this->patchJson("/api/v1/rider-app/rides/{$this->ongoing->id}/passengers/{$passenger->id}/accept")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$this->ongoing->id}/passengers/{$passenger->id}/decline")->assertUnprocessable();
});
