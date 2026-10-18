<?php

use App\Models\PricingRule;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Models\Zone;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use App\Services\Trip\NearDestinationService;
use Clickbar\Magellan\Data\Geometries\LineString;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Data\Geometries\Polygon;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\MessageTarget;
use Kreait\Firebase\Messaging\MulticastSendReport;
use Kreait\Firebase\Messaging\SendReport;
use Laravel\Sanctum\Sanctum;

/**
 * Push format (data-only Android, APNs alert, string data with type/id/title/body/sent_at),
 * job offers with ring windows and ride_offer_cancelled, the search timeout, and the
 * passenger lifecycle pushes.
 */
beforeEach(function () {
    // Records what would be sent, to whom.
    $this->push = new class(Mockery::mock(Messaging::class)) extends FcmGateway
    {
        /** @var list<array{0: int|null, 1: PushMessage}> */
        public array $sent = [];

        public function sendToUser(?User $user, PushMessage $message): void
        {
            $this->sent[] = [$user?->id, $message];
        }
    };
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
    $this->vehicleType = VehicleType::factory()->create(['capacity' => 1]);
    PricingRule::factory()->create(['zone_id' => $this->zone->id, 'vehicle_type_id' => $this->vehicleType->id]);

    $this->customer = User::factory()->customer()->create();
});

function onlineRider($test, float $latitude = 0.3410, float $longitude = 32.5825): User
{
    $rider = User::factory()->rider()->create();
    $profile = RiderProfile::factory()->create([
        'user_id' => $rider->id,
        'availability_status' => 'online',
        'current_location' => Point::makeGeodetic($latitude, $longitude),
    ]);
    Vehicle::factory()->create(['rider_profile_id' => $profile->id, 'vehicle_type_id' => $test->vehicleType->id]);

    return $rider;
}

function bookRide($test, array $extra = []): TripPassenger
{
    Sanctum::actingAs($test->customer);

    $id = $test->postJson('/api/v1/user-app/trips/order-ride', [
        'type' => 'ride',
        'vehicle_type_id' => $test->vehicleType->id,
        'pickup_latitude' => 0.3400, 'pickup_longitude' => 32.5825, 'pickup_address' => 'Kireka',
        'dropoff_latitude' => 0.3200, 'dropoff_longitude' => 32.5825, 'dropoff_address' => 'Ntinda',
        'distance_km' => 2.2,
        'payment_method' => 'cash',
        ...$extra,
    ])->assertCreated()->json('data.id');

    return TripPassenger::query()->findOrFail($id);
}

/**
 * @return list<PushMessage>
 */
function pushesTo($test, User $user, ?string $type = null): array
{
    return collect($test->push->sent)
        ->filter(fn (array $push) => $push[0] === $user->id && ($type === null || $push[1]->type === $type))
        ->map(fn (array $push) => $push[1])
        ->values()
        ->all();
}

test('a push is data-only on Android with string data, and carries an APNs alert', function () {
    $message = (new PushMessage(
        type: 'trip_completed',
        id: 42,
        title: 'You have arrived',
        body: 'Thanks for riding.',
        data: ['trip_id' => 7, 'fare' => 8500.5],
        sound: 'destination_beep.caf',
    ))->toCloudMessage();
    $message = json_decode(json_encode($message), true);

    expect($message)->not->toHaveKey('notification')
        ->and($message['data'])->toMatchArray([
            'type' => 'trip_completed', 'id' => '42', 'title' => 'You have arrived', 'body' => 'Thanks for riding.',
            'trip_id' => '7', 'fare' => '8500.5',
        ])
        ->and($message['data']['sent_at'])->toMatch('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/')
        ->and(collect($message['data'])->every(fn ($value) => is_string($value)))->toBeTrue()
        ->and($message['android']['priority'])->toBe('high');

    $apns = $message['apns'];

    expect($apns['headers'])->toMatchArray(['apns-priority' => '10', 'apns-push-type' => 'alert'])
        ->and($apns['payload']['aps'])->toMatchArray([
            'alert' => ['title' => 'You have arrived', 'body' => 'Thanks for riding.'],
            'sound' => 'destination_beep.caf',
        ]);
});

test('a silent push wakes iOS in the background with no alert or sound', function () {
    $message = json_decode(json_encode((new PushMessage(type: 'ride_offer_cancelled', id: 9, silent: true, ttlSeconds: 30))->toCloudMessage()), true);
    $apns = $message['apns'];

    expect($message['data'])->toHaveKeys(['type', 'id', 'sent_at'])->not->toHaveKeys(['title', 'body'])
        ->and($message['android'])->toMatchArray(['priority' => 'high', 'ttl' => '30s'])
        ->and($apns['headers'])->toMatchArray(['apns-priority' => '5', 'apns-push-type' => 'background'])
        ->and($apns['payload']['aps'])->toBe(['content-available' => 1]);
});

test('the gateway sends only to the user\'s active device', function () {
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')
        ->once()
        ->withArgs(fn (CloudMessage $message, array $tokens) => $tokens === ['active-token'])
        ->andReturn(MulticastSendReport::withItems([]));

    $user = User::factory()->create();
    UserDevice::query()->create(['user_id' => $user->id, 'device_type' => 'android', 'device_id' => 'old', 'fcm_token' => 'old-token', 'active' => false]);
    UserDevice::query()->create(['user_id' => $user->id, 'device_type' => 'android', 'device_id' => 'new', 'fcm_token' => 'active-token', 'active' => true, 'last_seen_at' => now()]);

    (new FcmGateway($messaging))->sendToUser($user, new PushMessage(type: 'trip_started', id: 1, title: 'T', body: 'B'));
});

test('booking rings every nearby rider with a timed job offer', function () {
    $near = onlineRider($this);
    $far = onlineRider($this, 0.69, 32.89);

    $passenger = bookRide($this);
    $trip = $passenger->trip;

    expect($trip->search_started_at)->not->toBeNull()
        ->and(TripOffer::query()->where('trip_id', $trip->id)->pluck('rider_id')->all())->toBe([$near->id]);

    $offers = pushesTo($this, $near, 'ride_offer');
    expect($offers)->toHaveCount(1);

    $offer = $offers[0];
    expect($offer->id)->toBe($trip->id)
        ->and($offer->title)->toBe('New job — 0.1 km away')
        ->and($offer->body)->toStartWith('Kireka → Ntinda · UGX ')
        ->and($offer->sound)->toBe('request_alert.caf')
        ->and($offer->interruptionLevel)->toBe('time-sensitive')
        ->and($offer->badge)->toBe(1)
        ->and($offer->collapseKey)->toBe("ride_{$trip->id}")
        ->and((int) round(now()->diffInSeconds($offer->expiresAt)))->toBe(30)
        ->and($offer->payloadData())->toMatchArray(['trip_type' => 'ride', 'trip_id' => (string) $trip->id]);

    expect(pushesTo($this, $far))->toBe([]);
});

test('the badge counts every offer still ringing for the rider', function () {
    $rider = onlineRider($this);
    bookRide($this);
    bookRide($this, ['pickup_address' => 'Bweyogerere']);

    expect(collect(pushesTo($this, $rider, 'ride_offer'))->pluck('badge')->all())->toBe([1, 2]);
});

test('accepting stops every other rider ringing and tells the customer', function () {
    $first = onlineRider($this);
    $second = onlineRider($this);
    $passenger = bookRide($this);

    Sanctum::actingAs($first);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();

    expect(TripOffer::query()->where('rider_id', $first->id)->value('status'))->toBe('accepted')
        ->and(TripOffer::query()->where('rider_id', $second->id)->value('status'))->toBe('withdrawn');

    $cancelled = pushesTo($this, $second, 'ride_offer_cancelled');
    expect($cancelled)->toHaveCount(1)
        ->and($cancelled[0]->silent)->toBeTrue()
        ->and($cancelled[0]->payloadData()['reason'])->toBe('taken');
    expect(pushesTo($this, $first, 'ride_offer_cancelled'))->toBe([]);

    $assigned = pushesTo($this, $this->customer, 'trip_rider_assigned');
    expect($assigned)->toHaveCount(1)
        ->and($assigned[0]->id)->toBe($passenger->id)
        ->and($assigned[0]->payloadData())->toMatchArray(['trip_id' => (string) $passenger->trip_id, 'trip_passenger_id' => (string) $passenger->id]);
});

test('an unanswered offer expires, and the next round only rings riders not yet offered it', function () {
    $first = onlineRider($this);
    $passenger = bookRide($this);

    $this->travel(31)->seconds();
    $latecomer = onlineRider($this);
    $this->artisan('trips:process-dispatch')->assertSuccessful();

    expect(TripOffer::query()->where('rider_id', $first->id)->value('status'))->toBe('expired')
        ->and(pushesTo($this, $first, 'ride_offer_cancelled')[0]->payloadData()['reason'])->toBe('expired')
        ->and(pushesTo($this, $first, 'ride_offer'))->toHaveCount(1)
        ->and(pushesTo($this, $latecomer, 'ride_offer'))->toHaveCount(1)
        ->and($passenger->trip->fresh()->status)->toBe('searching');

    // A rider whose offer stopped ringing can still accept while the trip searches.
    Sanctum::actingAs($first);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();
    expect(pushesTo($this, $latecomer, 'ride_offer_cancelled'))->toHaveCount(1);
});

test('a search that finds no rider in time is cancelled and the customer told', function () {
    $rider = onlineRider($this);
    $passenger = bookRide($this);

    $this->travel(5)->minutes();
    $this->travel(1)->seconds();
    $this->artisan('trips:process-dispatch')->assertSuccessful();

    expect($passenger->trip->fresh()->status)->toBe('cancelled')
        ->and($passenger->fresh()->status)->toBe('cancelled')
        ->and(TripOffer::query()->where('rider_id', $rider->id)->value('status'))->not->toBe('pending');

    $noRiders = pushesTo($this, $this->customer, 'trip_no_riders');
    expect($noRiders)->toHaveCount(1)->and($noRiders[0]->id)->toBe($passenger->id);
});

test('the customer cancelling a searching trip stops the riders ringing', function () {
    $rider = onlineRider($this);
    $passenger = bookRide($this);

    $this->patchJson("/api/v1/user-app/trips/cancel-ride/{$passenger->id}")->assertOk();

    expect(TripOffer::query()->where('rider_id', $rider->id)->value('status'))->toBe('withdrawn')
        ->and(pushesTo($this, $rider, 'ride_offer_cancelled')[0]->payloadData()['reason'])->toBe('cancelled');
});

test('a rider cancelling an accepted trip starts a new search without them', function () {
    $first = onlineRider($this);
    $passenger = bookRide($this);

    Sanctum::actingAs($first);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();

    $second = onlineRider($this);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/cancel")->assertOk();

    expect(pushesTo($this, $second, 'ride_offer'))->toHaveCount(1)
        ->and(pushesTo($this, $first, 'ride_offer'))->toHaveCount(1)
        ->and(pushesTo($this, $this->customer, 'trip_rider_cancelled'))->toHaveCount(1);
});

test('the passenger hears about arrival, the start and the end of the trip', function () {
    $rider = onlineRider($this);
    $passenger = bookRide($this);

    Sanctum::actingAs($rider);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/arrive")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/start")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/end")->assertOk();

    $arrived = pushesTo($this, $this->customer, 'trip_rider_arrived');
    $completed = pushesTo($this, $this->customer, 'trip_completed');

    expect($arrived[0]->interruptionLevel)->toBe('time-sensitive')
        ->and($arrived[0]->sound)->toBe('trip_alert.caf')
        ->and(pushesTo($this, $this->customer, 'trip_started'))->toHaveCount(1)
        ->and($completed)->toHaveCount(1)
        ->and($completed[0]->sound)->toBe('default')
        ->and($completed[0]->id)->toBe($passenger->id);
});

test('a riding passenger gets one push when the rider is near their drop-off', function () {
    $rider = onlineRider($this);
    $passenger = bookRide($this);

    Sanctum::actingAs($rider);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/start")->assertOk();

    // ~1.1 km out: nothing yet.
    $this->postJson("/api/v1/rider-app/rides/{$passenger->trip_id}/location", ['latitude' => 0.3300, 'longitude' => 32.5825])->assertCreated();
    expect(pushesTo($this, $this->customer, 'trip_near_destination'))->toBe([]);

    // ~330 m out, twice.
    $this->postJson("/api/v1/rider-app/rides/{$passenger->trip_id}/location", ['latitude' => 0.3230, 'longitude' => 32.5825])->assertCreated();
    $this->postJson("/api/v1/rider-app/rides/{$passenger->trip_id}/location", ['latitude' => 0.3220, 'longitude' => 32.5825])->assertCreated();

    $near = pushesTo($this, $this->customer, 'trip_near_destination');
    expect($near)->toHaveCount(1)
        ->and($near[0]->sound)->toBe('trip_alert.caf')
        ->and($near[0]->interruptionLevel)->toBe('time-sensitive')
        ->and($near[0]->body)->toBe('About 400 m to your drop-off. Get ready to step off.')
        ->and($passenger->fresh()->near_destination_notified_at)->not->toBeNull();
});

test('a location sent through the profile update also triggers the near-destination push', function () {
    $rider = onlineRider($this);
    $passenger = bookRide($this);

    Sanctum::actingAs($rider);
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/accept")->assertOk();
    $this->patchJson("/api/v1/rider-app/rides/{$passenger->trip_id}/start")->assertOk();

    $this->patchJson('/api/v1/rider-app/profile', ['latitude' => 0.3210, 'longitude' => 32.5825])->assertOk();

    expect(pushesTo($this, $this->customer, 'trip_near_destination'))->toHaveCount(1);
});

test('sending a push also writes it to the user\'s database notifications', function () {
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    $user = User::factory()->create();
    UserDevice::query()->create(['user_id' => $user->id, 'device_type' => 'android', 'device_id' => 'd', 'fcm_token' => 'token', 'active' => true]);

    (new FcmGateway($messaging))->sendToUser($user, new PushMessage(
        type: 'trip_completed',
        id: 42,
        title: 'You have arrived',
        body: 'Thanks for riding.',
        data: ['trip_id' => 7],
    ));

    expect($user->notifications()->count())->toBe(1);

    $notification = $user->notifications()->first();
    expect($notification->type)->toBe('trip_completed')
        ->and($notification->data)->toMatchArray(['id' => 42, 'title' => 'You have arrived', 'body' => 'Thanks for riding.', 'trip_id' => 7])
        ->and($notification->read_at)->toBeNull();
});

test('a silent push is not written to the database', function () {
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    $user = User::factory()->create();
    UserDevice::query()->create(['user_id' => $user->id, 'device_type' => 'android', 'device_id' => 'd', 'fcm_token' => 'token', 'active' => true]);

    (new FcmGateway($messaging))->sendToUser($user, new PushMessage(type: 'ride_offer_cancelled', id: 9, silent: true));

    expect($user->notifications()->count())->toBe(0);
});

test('a push opted out of persistence is not written to the database', function () {
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    $user = User::factory()->create();
    UserDevice::query()->create(['user_id' => $user->id, 'device_type' => 'android', 'device_id' => 'd', 'fcm_token' => 'token', 'active' => true]);

    (new FcmGateway($messaging))->sendToUser($user, new PushMessage(type: 'ride_offer', id: 9, title: 'New job', body: 'Nearby', persist: false));

    expect($user->notifications()->count())->toBe(0);
});

test('a push with no badge of its own gets the recipient\'s unread notification count', function () {
    $user = User::factory()->create();
    UserDevice::query()->create(['user_id' => $user->id, 'device_type' => 'android', 'device_id' => 'd', 'fcm_token' => 'token', 'active' => true]);

    $spy = new class(Mockery::mock(Messaging::class)) extends FcmGateway
    {
        public array $captured = [];

        public function sendToTokens(array $tokens, PushMessage $message): void
        {
            $this->captured[] = $message;
        }
    };

    // Each push is counted in the badge it carries itself -- the count reflects state
    // right after that push's own row was persisted.
    $spy->sendToUser($user, new PushMessage(type: 'trip_started', id: 1, title: 'T', body: 'B'));
    $spy->sendToUser($user, new PushMessage(type: 'trip_completed', id: 2, title: 'T2', body: 'B2'));

    expect($spy->captured[0]->badge)->toBe(1)
        ->and($spy->captured[1]->badge)->toBe(2);
});

test('a ride offer keeps its own badge instead of the unread notification count', function () {
    $user = User::factory()->create();
    UserDevice::query()->create(['user_id' => $user->id, 'device_type' => 'android', 'device_id' => 'd', 'fcm_token' => 'token', 'active' => true]);

    $spy = new class(Mockery::mock(Messaging::class)) extends FcmGateway
    {
        public array $captured = [];

        public function sendToTokens(array $tokens, PushMessage $message): void
        {
            $this->captured[] = $message;
        }
    };

    $spy->sendToUser($user, new PushMessage(type: 'ride_offer', id: 1, title: 'T', body: 'B', badge: 7, persist: false));

    expect($spy->captured[0]->badge)->toBe(7);
});

test('a token the provider reports as unregistered is deactivated', function () {
    $user = User::factory()->create();
    $device = UserDevice::query()->create(['user_id' => $user->id, 'device_type' => 'android', 'device_id' => 'd', 'fcm_token' => 'dead-token', 'active' => true]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([
        SendReport::failure(MessageTarget::with('token', 'dead-token'), NotFound::becauseTokenNotFound('dead-token')),
    ]));

    (new FcmGateway($messaging))->sendToUser($user, new PushMessage(type: 'trip_started', id: 1, title: 'T', body: 'B'));

    expect($device->refresh()->active)->toBeFalse();
});

test('deliveries get no near-destination push', function () {
    $trip = Trip::factory()->delivery()->create([
        'zone_id' => $this->zone->id,
        'vehicle_type_id' => $this->vehicleType->id,
        'rider_id' => onlineRider($this)->id,
        'status' => 'in_progress',
        'completed_at' => null,
    ]);

    app(NearDestinationService::class)->check($trip, Point::makeGeodetic(0.32, 32.5825));

    expect($this->push->sent)->toBe([]);
});
