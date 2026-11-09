<?php

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

/**
 * Support tickets reuse the same ChatMessageSent broadcast as trip chat (the channel's
 * participant check doesn't care about ChatRoom.type), extended here with a reply endpoint
 * and read receipts for the ticket's own creator. There's no agent-side reply yet (no
 * Filament UI exists for support tickets), so this only covers the customer/rider side.
 */
beforeEach(function () {
    $this->category = SupportCategory::query()->create(['name' => 'Account', 'code' => 'account', 'is_active' => true]);
    $this->customer = User::factory()->customer()->create();
});

function openTicket($test, User $user, string $app = 'user-app'): SupportTicket
{
    Sanctum::actingAs($user);

    return SupportTicket::query()->findOrFail(
        $test->postJson("/api/v1/{$app}/support-tickets", [
            'category_id' => $test->category->id,
            'subject' => 'Cannot update my profile photo',
            'message' => 'The app crashes when I try to upload a new photo.',
        ])->assertCreated()->json('data.id')
    );
}

test('a user can reply to their own open ticket', function () {
    $ticket = openTicket($this, $this->customer);

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/messages", ['message' => 'Any update?'])
        ->assertCreated()
        ->assertJsonPath('data.message', 'Any update?');

    expect(ChatMessage::query()->where('chat_room_id', $ticket->chatRoom->id)->count())->toBe(2);
    expect($ticket->chatRoom->fresh()->recent_message)->toBe('Any update?');
});

test('a user cannot reply to someone elses ticket', function () {
    $ticket = openTicket($this, $this->customer);
    $stranger = User::factory()->customer()->create();

    Sanctum::actingAs($stranger);
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/messages", ['message' => 'hi'])
        ->assertNotFound();
});

test('sending requires either a message or an attachment', function () {
    $ticket = openTicket($this, $this->customer);

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/messages", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('message');
});

test('a ticket is closed for replies once resolved or closed, but history stays readable', function () {
    $ticket = openTicket($this, $this->customer);
    $ticket->update(['status' => 'resolved']);

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/messages", ['message' => 'one more thing'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');

    $this->getJson("/api/v1/user-app/support-tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.chat_room.messages.0.message', 'The app crashes when I try to upload a new photo.');
});

test('replying dispatches a ChatMessageSent broadcast for the tickets room', function () {
    $ticket = openTicket($this, $this->customer);

    Event::fake([ChatMessageSent::class]);

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/messages", ['message' => 'Any update?'])->assertCreated();

    Event::assertDispatched(ChatMessageSent::class, fn (ChatMessageSent $event): bool => $event->message->chat_room_id === $ticket->chatRoom->id
        && $event->message->message === 'Any update?');
});

test('marking a ticket read stamps the user into messages not sent by them', function () {
    $ticket = openTicket($this, $this->customer);
    $agent = User::factory()->create();

    ChatMessage::query()->create([
        'chat_room_id' => $ticket->chatRoom->id,
        'sender_id' => $agent->id,
        'message' => 'We are looking into it.',
    ]);

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/read")
        ->assertOk()
        ->assertJsonPath('data.marked_read', 1);

    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/read")
        ->assertOk()
        ->assertJsonPath('data.marked_read', 0);
});

test('the rider app has the same reply and read endpoints', function () {
    $driver = User::factory()->rider()->create();
    $ticket = openTicket($this, $driver, 'rider-app');

    Sanctum::actingAs($driver);
    $this->postJson("/api/v1/rider-app/support-tickets/{$ticket->id}/messages", ['message' => 'Still waiting on this'])
        ->assertCreated();

    $this->postJson("/api/v1/rider-app/support-tickets/{$ticket->id}/read")
        ->assertOk()
        ->assertJsonPath('data.marked_read', 0);
});
