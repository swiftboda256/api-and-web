<?php

use App\Models\PricingRule;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripCancellationReason;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Push\FcmGateway;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Laravel\Sanctum\Sanctum;

/**
 * Posted rides: the driver posts a fixed origin -> destination ride at their own per-seat
 * fare; customers book seats, which the driver approves; once started it runs like a
 * ride-share trip and settles each passenger at the fixed fare.
 */
beforeEach(function () {
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

    $this->driver = User::factory()->create();
    $riderProfile = RiderProfile::factory()->create(['user_id' => $this->driver->id, 'availability_status' => 'online']);
    Vehicle::factory()->create(['rider_profile_id' => $riderProfile->id, 'vehicle_type_id' => $this->vehicleType->id]);

    $this->customer = User::factory()->create();
});

function postRide($test, array $overrides = []): Trip
{
    Sanctum::actingAs($test->driver);

    $response = $test->postJson('/api/v1/rider-app/rides/posted', [
        'origin_latitude' => 0.3476,
        'origin_longitude' => 32.5825,
        'origin_address' => 'Kampala',
        'destination_latitude' => 0.0512,
        'destination_longitude' => 32.4637,
        'destination_address' => 'Entebbe',
        'departs_at' => now()->addDay()->toIso8601String(),
        'seat_fare' => 7000,
        'available_seats' => 3,
        'distance_km' => 38,
        ...$overrides,
    ])->assertCreated();

    return Trip::query()->findOrFail($response->json('data.id'));
}

function bookSeats($test, Trip $trip, User $customer, int $seats = 1): TripPassenger
{
    Sanctum::actingAs($customer);

    $response = $test->postJson("/api/v1/user-app/trips/posted/{$trip->id}/book", [
        'seats_requested' => $seats,
        'payment_method' => 'cash',
    ])->assertCreated();

    return TripPassenger::query()->findOrFail($response->json('data.id'));
}

/**
 * Asserts a push carrying the given data 'type' was sent.
 */
function assertPushed($test, string $type): void
{
    $test->push->shouldHaveReceived('sendToTokens')
        ->withArgs(fn ($tokens, $title, $body, $data) => ($data['type'] ?? null) === $type)
        ->atLeast()->once();
}

function approveBooking($test, TripPassenger $passenger): void
{
    Sanctum::actingAs($test->driver);

    $test->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/passengers/{$passenger->id}/accept")->assertOk();
}

test('the driver posts an open ride with their own route, date and seat fare', function () {
    $trip = postRide($this);

    expect($trip->type)->toBe('posted_ride')
        ->and($trip->status)->toBe('open')
        ->and($trip->rider_id)->toBe($this->driver->id)
        ->and($trip->zone_id)->toBe($this->zone->id)
        ->and($trip->available_seats)->toBe(3)
        ->and($trip->passenger_count)->toBe(0)
        ->and((float) $trip->seat_fare)->toBe(7000.0)
        ->and($trip->departs_at->isFuture())->toBeTrue()
        ->and($trip->origin_address)->toBe('Kampala');

    // Posting a future ride doesn't take the driver off on-demand work.
    expect($this->driver->riderProfile->fresh()->availability_status)->toBe('online');
});

test('the driver cannot offer more seats than the vehicle has', function () {
    Sanctum::actingAs($this->driver);

    $this->postJson('/api/v1/rider-app/rides/posted', [
        'origin_latitude' => 0.3476, 'origin_longitude' => 32.5825,
        'destination_latitude' => 0.0512, 'destination_longitude' => 32.4637,
        'departs_at' => now()->addDay()->toIso8601String(),
        'seat_fare' => 7000, 'available_seats' => 5, 'distance_km' => 38,
    ])->assertUnprocessable()->assertJsonValidationErrors('available_seats');
});

test('customers browse open rides near their origin and destination', function () {
    $trip = postRide($this);
    Sanctum::actingAs($this->customer);

    $this->getJson('/api/v1/user-app/trips/posted?origin_latitude=0.35&origin_longitude=32.58&destination_latitude=0.05&destination_longitude=32.46')
        ->assertOk()
        ->assertJsonPath('data.rides.0.id', $trip->id)
        ->assertJsonPath('data.rides.0.available_seats', 3)
        ->assertJsonPath('data.rides.0.currency_code', 'UGX');

    // Far from the ride's origin.
    $this->getJson('/api/v1/user-app/trips/posted?origin_latitude=0.6&origin_longitude=32.8')
        ->assertOk()
        ->assertJsonCount(0, 'data.rides');

    // A driver doesn't see their own posted ride.
    Sanctum::actingAs($this->driver);
    $this->getJson('/api/v1/user-app/trips/posted')->assertOk()->assertJsonCount(0, 'data.rides');
});

test('booking holds the seats and waits for the driver to approve', function () {
    $trip = postRide($this);
    $passenger = bookSeats($this, $trip, $this->customer, seats: 2);

    expect($passenger->status)->toBe('pending_approval')
        ->and((float) $passenger->fareBreakdown->estimated_fare)->toBe(14000.0)
        ->and($passenger->fareBreakdown->currency_code)->toBe('UGX')
        ->and($trip->fresh()->available_seats)->toBe(1)
        ->and($trip->fresh()->passenger_count)->toBe(0);

    // Pending stops are copied from the trip but not on the route yet.
    expect(TripStop::query()->where('trip_passenger_id', $passenger->id)->whereNull('sequence')->count())->toBe(2);

    // Booking twice is refused.
    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/trips/posted/{$trip->id}/book", ['payment_method' => 'cash'])
        ->assertUnprocessable();

    // Nor can anyone book more seats than are left.
    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/user-app/trips/posted/{$trip->id}/book", ['seats_requested' => 2, 'payment_method' => 'cash'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('seats_requested');
});

test('approving a booking matches the passenger and puts their stops on the route', function () {
    $trip = postRide($this);
    $first = bookSeats($this, $trip, $this->customer);
    $second = bookSeats($this, $trip, User::factory()->create());

    approveBooking($this, $first);
    approveBooking($this, $second);

    assertPushed($this, 'posted_ride_booking_approved');

    expect($first->fresh()->status)->toBe('matched')
        ->and($first->fresh()->request_expires_at)->toBeNull()
        ->and($trip->fresh()->passenger_count)->toBe(2)
        ->and($trip->fresh()->available_seats)->toBe(1);

    // All pickups (at the origin) before all dropoffs (at the destination).
    expect($trip->fresh()->stops->map(fn (TripStop $stop) => [$stop->trip_passenger_id, $stop->stop_type])->all())->toBe([
        [$first->id, 'pickup'],
        [$second->id, 'pickup'],
        [$first->id, 'dropoff'],
        [$second->id, 'dropoff'],
    ]);
});

test('declining a booking cancels it and returns its seats', function () {
    $trip = postRide($this);
    $passenger = bookSeats($this, $trip, $this->customer, seats: 2);

    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$passenger->id}/decline")->assertOk();

    expect($passenger->fresh()->status)->toBe('cancelled')
        ->and($passenger->fresh()->cancelled_by)->toBe($this->driver->id)
        ->and($trip->fresh()->available_seats)->toBe(3)
        ->and($trip->fresh()->status)->toBe('open');
});

test('a customer cancelling their booking frees the seats and the ride stays open', function () {
    $trip = postRide($this);
    $passenger = bookSeats($this, $trip, $this->customer, seats: 2);
    approveBooking($this, $passenger);

    Sanctum::actingAs($this->customer);
    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$trip->id}")->assertOk();

    $trip->refresh();

    expect($passenger->fresh()->status)->toBe('cancelled')
        ->and($trip->status)->toBe('open')
        ->and($trip->available_seats)->toBe(3)
        ->and($trip->passenger_count)->toBe(0)
        ->and($trip->stops)->toBeEmpty();
});

test('the ride can only start with an approved booking, and unanswered requests are declined then', function () {
    $trip = postRide($this);
    $pending = bookSeats($this, $trip, User::factory()->create());

    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/start")->assertUnprocessable();

    $approved = bookSeats($this, $trip, $this->customer);
    approveBooking($this, $approved);

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/start")->assertOk();

    expect($trip->fresh()->status)->toBe('in_progress')
        ->and($pending->fresh()->status)->toBe('cancelled')
        ->and($approved->fresh()->status)->toBe('matched')
        ->and($this->driver->riderProfile->fresh()->availability_status)->toBe('on_trip');

    // Too late to book once it's under way.
    Sanctum::actingAs(User::factory()->create());
    $this->postJson("/api/v1/user-app/trips/posted/{$trip->id}/book", ['payment_method' => 'cash'])->assertNotFound();
});

test('each passenger settles at the fixed seat fare and the ride completes after the last dropoff', function () {
    $trip = postRide($this);
    $first = bookSeats($this, $trip, $this->customer, seats: 2);
    $second = bookSeats($this, $trip, User::factory()->create());
    approveBooking($this, $first);
    approveBooking($this, $second);

    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/start")->assertOk();

    foreach ([$first, $second] as $passenger) {
        $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$passenger->id}/pickup")->assertOk();
    }

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$first->id}/dropoff")->assertOk();
    expect($trip->fresh()->status)->toBe('in_progress');

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$second->id}/dropoff")->assertOk();

    expect($trip->fresh()->status)->toBe('completed')
        ->and((float) $first->fareBreakdown->fresh()->final_fare)->toBe(14000.0)
        ->and((float) $second->fareBreakdown->fresh()->final_fare)->toBe(7000.0)
        // 9% commission from the pricing rule.
        ->and((float) $first->fareBreakdown->fresh()->rider_earning)->toBe(12740.0)
        ->and($this->driver->riderProfile->fresh()->availability_status)->toBe('online');
});

test('the driver cancelling the ride cancels every booking and does not re-dispatch it', function () {
    $trip = postRide($this);
    $approved = bookSeats($this, $trip, $this->customer);
    approveBooking($this, $approved);
    $pending = bookSeats($this, $trip, User::factory()->create());

    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/cancel")->assertOk();

    $trip->refresh();

    expect($trip->status)->toBe('cancelled')
        ->and($trip->rider_id)->toBe($this->driver->id)
        ->and($approved->fresh()->status)->toBe('cancelled')
        ->and($pending->fresh()->status)->toBe('cancelled');
});

test('a booking request expires after the configured timeout, but never after departure', function () {
    $trip = postRide($this);
    $passenger = bookSeats($this, $trip, $this->customer);

    expect($passenger->request_expires_at->diffInMinutes(now(), true))->toEqualWithDelta(60, 1);

    $soon = postRide($this, ['departs_at' => now()->addMinutes(20)->toIso8601String()]);
    $soonPassenger = bookSeats($this, $soon, $this->customer);

    expect($soonPassenger->request_expires_at->equalTo($soon->departs_at))->toBeTrue();
});

test('expired requests are cancelled, release their seats and notify the customer', function () {
    $trip = postRide($this);
    $passenger = bookSeats($this, $trip, $this->customer, seats: 2);

    // Not yet due -- left alone, including by the ride-share expiry job.
    $this->artisan('posted-ride:expire-requests')->assertSuccessful();
    $this->artisan('ride-share:expire-join-requests')->assertSuccessful();
    expect($passenger->fresh()->status)->toBe('pending_approval');

    $this->travel(61)->minutes();
    $this->artisan('posted-ride:expire-requests')->assertSuccessful();

    expect($passenger->fresh()->status)->toBe('cancelled')
        ->and($passenger->fresh()->cancelled_by)->toBeNull()
        ->and($trip->fresh()->available_seats)->toBe(3)
        ->and($trip->fresh()->status)->toBe('open');

    assertPushed($this, 'posted_ride_booking_expired');
});

test('an expired request can no longer be approved', function () {
    $trip = postRide($this);
    $passenger = bookSeats($this, $trip, $this->customer);

    $this->travel(61)->minutes();

    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$passenger->id}/accept")
        ->assertUnprocessable()
        ->assertJsonPath('errors.status.0', 'This request has expired.');
});

test('the driver removes an approved passenger before departure, freeing their seats', function () {
    $reason = TripCancellationReason::query()->create(['label' => 'Passenger did not show up', 'applies_to' => 'rider', 'is_active' => true]);
    $trip = postRide($this);
    $passenger = bookSeats($this, $trip, $this->customer, seats: 2);
    approveBooking($this, $passenger);

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$passenger->id}/remove", ['cancellation_reason_id' => $reason->id])
        ->assertOk()
        ->assertJsonPath('message', 'Passenger removed.')
        ->assertJsonPath('data.status', 'cancelled')
        ->assertJsonPath('data.cancellation_reason', 'Passenger did not show up');

    $trip->refresh();

    expect($passenger->fresh()->cancelled_by)->toBe($this->driver->id)
        ->and($passenger->fareBreakdown->fresh()->final_fare)->toBeNull()
        ->and($trip->status)->toBe('open')
        ->and($trip->available_seats)->toBe(3)
        ->and($trip->passenger_count)->toBe(0)
        ->and($trip->stops)->toBeEmpty();

    assertPushed($this, 'posted_ride_passenger_removed');

    // The ride is bookable again.
    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/user-app/trips/posted?seats=3')->assertOk()->assertJsonPath('data.rides.0.id', $trip->id);
});

test('only approved passengers not yet picked up can be removed, with a rider reason', function () {
    $customerReason = TripCancellationReason::query()->create(['label' => 'Changed my mind', 'applies_to' => 'customer', 'is_active' => true]);
    $trip = postRide($this);
    $pending = bookSeats($this, $trip, $this->customer);

    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$pending->id}/remove")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    approveBooking($this, $pending);
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$pending->id}/remove", ['cancellation_reason_id' => $customerReason->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cancellation_reason_id');

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/start")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$pending->id}/pickup")->assertOk();

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$pending->id}/remove")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('removing a no-show after departure completes the ride once everyone else is dropped off', function () {
    $trip = postRide($this);
    $rider = bookSeats($this, $trip, $this->customer);
    $noShow = bookSeats($this, $trip, User::factory()->create());
    approveBooking($this, $rider);
    approveBooking($this, $noShow);

    Sanctum::actingAs($this->driver);
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/start")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$rider->id}/pickup")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$rider->id}/dropoff")->assertOk();

    expect($trip->fresh()->status)->toBe('in_progress');

    $this->patchJson("/api/v1/rider-app/rides/{$trip->id}/passengers/{$noShow->id}/remove")->assertOk();

    expect($trip->fresh()->status)->toBe('completed')
        ->and($noShow->fresh()->status)->toBe('cancelled')
        ->and($this->driver->riderProfile->fresh()->availability_status)->toBe('online');
});
