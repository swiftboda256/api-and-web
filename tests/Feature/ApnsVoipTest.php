<?php

use App\Models\User;
use App\Models\UserDevice;
use App\Services\Push\ApnsVoipGateway;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use Illuminate\Support\Facades\Http;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\MulticastSendReport;

/**
 * VoIP (PushKit) pushes, sent directly to Apple so a CallKit call screen rings even with
 * the app swiped away -- the one thing FCM can't do. See
 * docs/new_IOS_PUSH_BACKEND_GUIDE.md §10.
 */
const TEST_APNS_KEY = <<<'PEM'
-----BEGIN PRIVATE KEY-----
MIGHAgEAMBMGByqGSM49AgEGCCqGSM49AwEHBG0wawIBAQQgQmGszodQ1Si56DYq
POFx4VvuPvsFxUtKWuvB3wjsKrWhRANCAAS+d29pffHYmZNVEmF3EwQJG7StdvKh
V8ZQ/D9PS0O6rNN/xcnAQiu7izzfUNHlpbvrXg1ptG61keMLCiEV8UJ/
-----END PRIVATE KEY-----
PEM;

function configureApns(): void
{
    $path = sys_get_temp_dir().'/apns_voip_test_key.p8';
    file_put_contents($path, TEST_APNS_KEY);

    config([
        'apns.key_id' => 'TESTKEYID',
        'apns.team_id' => 'TESTTEAMID',
        'apns.auth_key_path' => $path,
    ]);
}

function riderWithVoipDevice(?string $voipToken = 'voip-token'): User
{
    $rider = User::factory()->rider()->create();
    UserDevice::query()->create([
        'user_id' => $rider->id,
        'device_type' => 'ios',
        'device_id' => 'd',
        'fcm_token' => 'fcm-token',
        'voip_token' => $voipToken,
        'active' => true,
    ]);

    return $rider;
}

test('a configured but unreadable key path sends FCM only, unchanged', function () {
    config([
        'apns.key_id' => 'TESTKEYID',
        'apns.team_id' => 'TESTTEAMID',
        'apns.auth_key_path' => '/nonexistent/AuthKey.p8',
    ]);
    $rider = riderWithVoipDevice();
    Http::fake();

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($rider, new PushMessage(
        type: 'ride_offer', id: 1, title: 'New job', body: 'Nearby', persist: false,
    ));

    Http::assertNothingSent();
});

test('a ringing type with no APNs credentials configured sends FCM only, unchanged', function () {
    $rider = riderWithVoipDevice();
    Http::fake();

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')
        ->once()
        ->withArgs(fn ($message, array $tokens) => $tokens === ['fcm-token'])
        ->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($rider, new PushMessage(
        type: 'ride_offer', id: 1, title: 'New job', body: 'Nearby', persist: false,
    ));

    Http::assertNothingSent();
});

test('a ringing type with a voip token also rings via APNs, and drops the FCM sound', function () {
    configureApns();
    $rider = riderWithVoipDevice();
    Http::fake(['*' => Http::response(['status' => 'success'], 200)]);

    $capturedFcm = null;
    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')
        ->once()
        ->withArgs(function ($message) use (&$capturedFcm) {
            $capturedFcm = json_decode(json_encode($message), true);

            return true;
        })
        ->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($rider, new PushMessage(
        type: 'ride_offer', id: 436, title: 'New job', body: 'Nearby', sound: 'request_alert.caf', persist: false,
    ));

    // FCM alert no longer carries a sound -- the VoIP call does the ringing instead.
    expect($capturedFcm['apns']['payload']['aps'])->not->toHaveKey('sound');

    Http::assertSent(function ($request) {
        return $request->url() === 'https://api.sandbox.push.apple.com/3/device/voip-token'
            && $request->hasHeader('apns-push-type', 'voip')
            && $request->hasHeader('apns-priority', '10')
            && $request->hasHeader('apns-expiration', '0')
            && str_starts_with((string) $request->header('apns-topic')[0], 'com.swiftboda.swiftBodaRider')
            && str_ends_with((string) $request->header('apns-topic')[0], '.voip')
            && $request['type'] === 'ride_offer'
            && $request['id'] === '436'
            && (array) $request->data()['aps'] === [];
    });
});

test('a passenger\'s voip push uses the passenger bundle id', function () {
    configureApns();
    $customer = User::factory()->customer()->create();
    UserDevice::query()->create([
        'user_id' => $customer->id,
        'device_type' => 'ios',
        'device_id' => 'd',
        'fcm_token' => 'fcm-token',
        'voip_token' => 'voip-token',
        'active' => true,
    ]);
    Http::fake(['*' => Http::response([], 200)]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($customer, new PushMessage(
        type: 'trip_rider_arrived', id: 421, title: 'Your rider has arrived', body: 'Waiting', sound: 'trip_alert.caf',
    ));

    Http::assertSent(fn ($request) => str_starts_with((string) $request->header('apns-topic')[0], 'com.swiftboda.swiftBoda.dev'));
});

test('a non-ringing type never rings via APNs even with a voip token on file', function () {
    configureApns();
    $rider = riderWithVoipDevice();
    Http::fake();

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($rider, new PushMessage(
        type: 'ride_completed', id: 1, title: 'Trip completed', body: 'Done',
    ));

    Http::assertNothingSent();
});

test('a device with no voip token never rings via APNs', function () {
    configureApns();
    $rider = riderWithVoipDevice(voipToken: null);
    Http::fake();

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($rider, new PushMessage(
        type: 'ride_offer', id: 1, title: 'New job', body: 'Nearby', persist: false,
    ));

    Http::assertNothingSent();
});

test('a 410 from Apple clears the voip token so it stops being tried', function () {
    configureApns();
    $rider = riderWithVoipDevice();
    Http::fake(['*' => Http::response(['reason' => 'Unregistered'], 410)]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($rider, new PushMessage(
        type: 'ride_offer', id: 1, title: 'New job', body: 'Nearby', persist: false,
    ));

    expect($rider->devices()->first()->voip_token)->toBeNull();
});

test('a 400 BadDeviceToken from Apple clears the voip token', function () {
    configureApns();
    $rider = riderWithVoipDevice();
    Http::fake(['*' => Http::response(['reason' => 'BadDeviceToken'], 400)]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($rider, new PushMessage(
        type: 'ride_offer', id: 1, title: 'New job', body: 'Nearby', persist: false,
    ));

    expect($rider->devices()->first()->voip_token)->toBeNull();
});

test('an unrelated APNs failure logs but leaves the voip token alone', function () {
    configureApns();
    $rider = riderWithVoipDevice();
    Http::fake(['*' => Http::response(['reason' => 'InternalServerError'], 500)]);

    $messaging = Mockery::mock(Messaging::class);
    $messaging->shouldReceive('sendMulticast')->once()->andReturn(MulticastSendReport::withItems([]));

    (new FcmGateway($messaging, new ApnsVoipGateway))->sendToUser($rider, new PushMessage(
        type: 'ride_offer', id: 1, title: 'New job', body: 'Nearby', persist: false,
    ));

    expect($rider->devices()->first()->voip_token)->toBe('voip-token');
});
