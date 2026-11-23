<?php

use App\Jobs\DispatchScheduledTripJob;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\Push\FcmGateway;
use App\Services\Trip\TripService;
use Clickbar\Magellan\Data\Geometries\Point;

beforeEach(function () {
    $this->app->instance(FcmGateway::class, Mockery::spy(FcmGateway::class));
    $this->vehicleType = VehicleType::factory()->create();
    $this->trip = Trip::factory()->create([
        'type' => 'ride', 'status' => 'requested', 'rider_id' => null,
        'vehicle_type_id' => $this->vehicleType->id, 'requested_at' => now(),
    ]);
    $passenger = TripPassenger::query()->create([
        'trip_id' => $this->trip->id, 'customer_id' => User::factory()->create()->id,
        'seats_requested' => 1, 'status' => 'requested', 'requested_at' => now(),
    ]);
    TripStop::query()->create([
        'trip_id' => $this->trip->id, 'trip_passenger_id' => $passenger->id, 'stop_type' => 'pickup',
        'seats_delta' => 1, 'sequence' => 1, 'location' => Point::makeGeodetic(0.34, 32.5825),
    ]);
});

function riderAt($test, float $latitude, float $longitude): User
{
    $rider = User::factory()->rider()->create();
    $profile = RiderProfile::factory()->create([
        'user_id' => $rider->id, 'availability_status' => 'online',
        'current_location' => Point::makeGeodetic($latitude, $longitude),
    ]);
    Vehicle::factory()->create(['rider_profile_id' => $profile->id, 'vehicle_type_id' => $test->vehicleType->id]);

    return $rider;
}

test('a scheduled trip whose dispatch fails is picked up by the dispatch loop', function () {
    $rider = riderAt($this, 0.341, 32.5825);
    $fail = true;
    Trip::updating(function () use (&$fail): void {
        throw_if($fail, RuntimeException::class, 'boom');
    });

    (new DispatchScheduledTripJob($this->trip->id))->handle(app(TripService::class));
    $fail = false;
    expect($this->trip->fresh()->status)->toBe('searching');

    $this->artisan('trips:process-dispatch');

    expect(TripOffer::query()->where('trip_id', $this->trip->id)->pluck('rider_id')->all())->toBe([$rider->id]);
});
