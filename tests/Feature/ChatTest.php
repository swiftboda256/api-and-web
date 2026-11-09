<?php

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\ChatRoomParticipant;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * Trip-scoped chat between a driver and a customer. A room is created lazily, only on the
 * first message sent into it -- never when the driver is matched. A ride_share/delivery_share
 * trip with several passengers gets one private room per driver-passenger pair, never a group
 * chat. Chat is write-only between being matched and the booking ending; history stays
 * readable afterwards, only new messages are blocked.
 */
beforeEach(function () {
    $this->push = Mockery::spy(FcmGateway::class);
    $this->app->instance(FcmGateway::class, $this->push);

    $this->driver = User::factory()->rider()->create();
    $this->customer = User::factory()->customer()->create();
    $this->trip = Trip::factory()->create(['customer_id' => null, 'rider_id' => $this->driver->id]);
    $this->passenger = matchedPassenger($this->trip, $this->customer);
});

function matchedPassenger(Trip $trip, User $customer): TripPassenger
{
    return TripPassenger::query()->create([
        'trip_id' => $trip->id,
        'customer_id' => $customer->id,
        'seats_requested' => 1,
        'status' => 'matched',
        'requested_at' => now(),
    ]);
}

function sendAsCustomer($test, int $bookingId, array $payload)
{
    Sanctum::actingAs($test->customer);

    return $test->postJson("/api/v1/user-app/trips/{$bookingId}/chat/messages", $payload);
}

function sendAsDriver($test, int $tripId, int $passengerId, array $payload)
{
    Sanctum::actingAs($test->driver);

    return $test->postJson("/api/v1/rider-app/rides/{$tripId}/passengers/{$passengerId}/chat/messages", $payload);
}

// --- Sending / lazy room creation -------------------------------------------------------

test('a customer can send the first message, lazily creating the chat room', function () {
    expect(ChatRoom::query()->count())->toBe(0);

    sendAsCustomer($this, $this->passenger->id, ['message' => 'Hello, where are you?'])
        ->assertCreated()
        ->assertJsonPath('data.message', 'Hello, where are you?');

    expect(ChatRoom::query()->count())->toBe(1);

    $room = ChatRoom::query()->first();
    expect($room->type)->toBe('trip')
        ->and($room->trip_id)->toBe($this->trip->id)
        ->and($room->recent_message)->toBe('Hello, where are you?');

    expect(ChatRoomParticipant::query()->where('chat_room_id', $room->id)->pluck('user_id')->sort()->values()->all())
        ->toBe(collect([$this->customer->id, $this->driver->id])->sort()->values()->all());
});

test('a driver can reply in the same room', function () {
    sendAsCustomer($this, $this->passenger->id, ['message' => 'Hello'])->assertCreated();

    sendAsDriver($this, $this->trip->id, $this->passenger->id, ['message' => 'On my way'])
        ->assertCreated()
        ->assertJsonPath('data.message', 'On my way');

    expect(ChatRoom::query()->count())->toBe(1);

    $room = ChatRoom::query()->first();
    expect($room->recent_message)->toBe('On my way');
});

test('sending with only attachments and no message text is allowed', function () {
    sendAsCustomer($this, $this->passenger->id, [
        'attachments' => ['https://example.com/photo.jpg'],
    ])->assertCreated()->assertJsonPath('data.message', null);
});

test('sending requires either a message or an attachment', function () {
    sendAsCustomer($this, $this->passenger->id, [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

// --- Authorization and the chat window ---------------------------------------------------

test('a customer with no booking on the trip cannot see or send to its chat', function () {
    $stranger = User::factory()->customer()->create();

    Sanctum::actingAs($stranger);

    $this->getJson("/api/v1/user-app/trips/{$this->passenger->id}/chat")->assertNotFound();
    $this->getJson("/api/v1/user-app/trips/{$this->passenger->id}/chat/messages")->assertNotFound();
    $this->postJson("/api/v1/user-app/trips/{$this->passenger->id}/chat/messages", ['message' => 'hi'])->assertNotFound();
});

test('a driver who did not drive the trip cannot list or send to its chat rooms', function () {
    $otherDriver = User::factory()->rider()->create();

    Sanctum::actingAs($otherDriver);

    $this->getJson("/api/v1/rider-app/rides/{$this->trip->id}/chat")->assertNotFound();
    $this->getJson("/api/v1/rider-app/rides/{$this->trip->id}/passengers/{$this->passenger->id}/chat/messages")->assertNotFound();
    $this->postJson("/api/v1/rider-app/rides/{$this->trip->id}/passengers/{$this->passenger->id}/chat/messages", ['message' => 'hi'])->assertNotFound();
});

test('a different customer cannot read another passengers messages on the same trip', function () {
    $tripShare = Trip::factory()->rideShare()->create(['customer_id' => null, 'rider_id' => $this->driver->id]);
    $passengerA = matchedPassenger($tripShare, $this->customer);
    $customerB = User::factory()->customer()->create();
    $passengerB = matchedPassenger($tripShare, $customerB);

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/trips/{$passengerA->id}/chat/messages", ['message' => 'from A'])->assertCreated();

    Sanctum::actingAs($customerB);
    $this->getJson("/api/v1/user-app/trips/{$passengerB->id}/chat")->assertOk()->assertJsonPath('data.recent_message', null);
});

test('chat is rejected before the booking is matched', function () {
    $passenger = TripPassenger::query()->create([
        'trip_id' => $this->trip->id,
        'customer_id' => $this->customer->id,
        'seats_requested' => 1,
        'status' => 'pending_approval',
        'requested_at' => now(),
    ]);

    Sanctum::actingAs($this->customer);

    $this->postJson("/api/v1/user-app/trips/{$passenger->id}/chat/messages", ['message' => 'hi'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});

test('chat is closed for sending once the booking is cancelled, but history stays readable', function () {
    sendAsCustomer($this, $this->passenger->id, ['message' => 'still here?'])->assertCreated();

    $this->passenger->update(['status' => 'cancelled']);

    sendAsCustomer($this, $this->passenger->id, ['message' => 'one more'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    Sanctum::actingAs($this->customer);
    $this->getJson("/api/v1/user-app/trips/{$this->passenger->id}/chat/messages")
        ->assertOk()
        ->assertJsonPath('data.messages.0.message', 'still here?');
});

test('chat is closed for sending once the booking is dropped off, but history stays readable', function () {
    sendAsCustomer($this, $this->passenger->id, ['message' => 'thanks!'])->assertCreated();

    $this->passenger->update(['status' => 'dropped_off', 'dropped_off_at' => now()]);

    sendAsDriver($this, $this->trip->id, $this->passenger->id, ['message' => 'glad you made it'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    Sanctum::actingAs($this->driver);
    $this->getJson("/api/v1/rider-app/rides/{$this->trip->id}/passengers/{$this->passenger->id}/chat/messages")
        ->assertOk()
        ->assertJsonPath('data.messages.0.message', 'thanks!');
});

// --- Multi-passenger isolation -------------------------------------------------------------

test('the driver sees one room per passenger, listed independently', function () {
    $tripShare = Trip::factory()->rideShare()->create(['customer_id' => null, 'rider_id' => $this->driver->id]);
    $passengers = collect(range(1, 3))->map(fn () => matchedPassenger($tripShare, User::factory()->customer()->create()));

    Sanctum::actingAs($passengers[0]->customer);
    $this->postJson("/api/v1/user-app/trips/{$passengers[0]->id}/chat/messages", ['message' => 'hi from 1'])->assertCreated();

    Sanctum::actingAs($this->driver);
    $response = $this->getJson("/api/v1/rider-app/rides/{$tripShare->id}/chat")->assertOk();

    $rooms = $response->json('data');
    expect($rooms)->toHaveCount(3);

    $bookingIds = collect($rooms)->pluck('booking_id')->sort()->values()->all();
    expect($bookingIds)->toBe($passengers->pluck('id')->sort()->values()->all());

    $roomForFirst = collect($rooms)->firstWhere('booking_id', $passengers[0]->id);
    expect($roomForFirst['recent_message'])->toBe('hi from 1');

    $roomForSecond = collect($rooms)->firstWhere('booking_id', $passengers[1]->id);
    expect($roomForSecond['id'])->toBeNull()
        ->and($roomForSecond['recent_message'])->toBeNull();
});

test('a message to one passenger never appears in another passengers room or message list', function () {
    $tripShare = Trip::factory()->rideShare()->create(['customer_id' => null, 'rider_id' => $this->driver->id]);
    $passengers = collect(range(1, 3))->map(fn () => matchedPassenger($tripShare, User::factory()->customer()->create()));

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/v1/rider-app/rides/{$tripShare->id}/passengers/{$passengers[0]->id}/chat/messages", ['message' => 'to passenger 1'])->assertCreated();
    $this->postJson("/api/v1/rider-app/rides/{$tripShare->id}/passengers/{$passengers[1]->id}/chat/messages", ['message' => 'to passenger 2'])->assertCreated();

    expect(ChatRoom::query()->count())->toBe(2);

    Sanctum::actingAs($passengers[1]->customer);
    $messages = $this->getJson("/api/v1/user-app/trips/{$passengers[1]->id}/chat/messages")->assertOk()->json('data.messages');

    expect(collect($messages)->pluck('message')->all())->toBe(['to passenger 2']);
});

test('each passenger only ever has their own single room for the trip', function () {
    $tripShare = Trip::factory()->rideShare()->create(['customer_id' => null, 'rider_id' => $this->driver->id]);
    $passenger = matchedPassenger($tripShare, User::factory()->customer()->create());

    Sanctum::actingAs($passenger->customer);
    $this->postJson("/api/v1/user-app/trips/{$passenger->id}/chat/messages", ['message' => 'first'])->assertCreated();
    $this->postJson("/api/v1/user-app/trips/{$passenger->id}/chat/messages", ['message' => 'second'])->assertCreated();

    expect(ChatRoom::query()->where('trip_id', $tripShare->id)->whereHas('participants', fn ($q) => $q->where('user_id', $passenger->customer_id))->count())->toBe(1);

    $show = $this->getJson("/api/v1/user-app/trips/{$passenger->id}/chat")->assertOk();
    expect($show->json('data.recent_message'))->toBe('second');
});

// --- Broadcasting ---------------------------------------------------------------------------

test('sending a message dispatches a ChatMessageSent broadcast for its room', function () {
    Event::fake([ChatMessageSent::class]);

    sendAsCustomer($this, $this->passenger->id, ['message' => 'hello'])->assertCreated();

    $room = ChatRoom::query()->first();

    Event::assertDispatched(ChatMessageSent::class, fn (ChatMessageSent $event): bool => $event->message->chat_room_id === $room->id
        && $event->message->message === 'hello');
});

test('the broadcast channel for a chat room only authorizes its participants', function () {
    sendAsCustomer($this, $this->passenger->id, ['message' => 'hello'])->assertCreated();

    $room = ChatRoom::query()->first();

    // Tests force BROADCAST_CONNECTION=null (see phpunit.xml) so sending the message above
    // never made a real connection; the null broadcaster's auth() is a pure no-op too, so it
    // never exercises routes/channels.php. Switching to reverb here still never talks to a
    // server -- channel auth is a local HMAC signature (PusherBroadcaster::auth()), so this
    // exercises the real authorization closure without any network call. Each broadcaster
    // connection keeps its own registered channels, so routes/channels.php (loaded against
    // the null driver at boot) has to be re-required against the now-current one.
    config(['broadcasting.default' => 'reverb']);
    require base_path('routes/channels.php');

    Sanctum::actingAs($this->customer);
    $this->postJson('/broadcasting/auth', [
        'channel_name' => "private-chat-room.{$room->id}",
        'socket_id' => '123.456',
    ])->assertOk();

    $stranger = User::factory()->customer()->create();
    Sanctum::actingAs($stranger);
    $this->postJson('/broadcasting/auth', [
        'channel_name' => "private-chat-room.{$room->id}",
        'socket_id' => '123.456',
    ])->assertForbidden();
});

// --- Push notifications ----------------------------------------------------------------------

test('a customer sending a chat message pushes the driver', function () {
    sendAsCustomer($this, $this->passenger->id, ['message' => 'where are you?'])->assertCreated();

    $this->push->shouldHaveReceived('sendToUser')
        ->withArgs(fn ($user, PushMessage $message) => $user->id === $this->driver->id
            && $message->type === 'chat_message_received'
            && $message->body === 'where are you?')
        ->once();
});

test('a driver sending a chat message pushes the customer', function () {
    sendAsDriver($this, $this->trip->id, $this->passenger->id, ['message' => 'on my way'])->assertCreated();

    $this->push->shouldHaveReceived('sendToUser')
        ->withArgs(fn ($user, PushMessage $message) => $user->id === $this->customer->id
            && $message->type === 'chat_message_received'
            && $message->body === 'on my way')
        ->once();
});

// --- Read receipts -------------------------------------------------------------------------

test('marking a room read stamps the current user into the others messages, skipping their own', function () {
    sendAsCustomer($this, $this->passenger->id, ['message' => 'hi'])->assertCreated();

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/v1/rider-app/rides/{$this->trip->id}/passengers/{$this->passenger->id}/chat/read")
        ->assertOk()
        ->assertJsonPath('data.marked_read', 1);

    $messages = ChatMessage::query()->get();
    expect($messages)->toHaveCount(1);
    expect($messages->first()->read_by)->toBe([$this->customer->id, $this->driver->id]);
});

test('marking read is idempotent', function () {
    sendAsCustomer($this, $this->passenger->id, ['message' => 'hi'])->assertCreated();

    Sanctum::actingAs($this->driver);
    $this->postJson("/api/v1/rider-app/rides/{$this->trip->id}/passengers/{$this->passenger->id}/chat/read")
        ->assertOk()->assertJsonPath('data.marked_read', 1);

    $this->postJson("/api/v1/rider-app/rides/{$this->trip->id}/passengers/{$this->passenger->id}/chat/read")
        ->assertOk()->assertJsonPath('data.marked_read', 0);

    expect(ChatMessage::query()->first()->read_by)->toBe([$this->customer->id, $this->driver->id]);
});

test('marking read on a room with no messages yet is a no-op', function () {
    Sanctum::actingAs($this->customer);

    $this->postJson("/api/v1/user-app/trips/{$this->passenger->id}/chat/read")
        ->assertOk()
        ->assertJsonPath('data.marked_read', 0);
});
