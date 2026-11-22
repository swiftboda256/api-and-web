<?php

use App\Events\ChatMessageSent;
use App\Filament\Pages\Support\SupportTicketDetails;
use App\Filament\Pages\Support\SupportTickets;
use App\Models\ChatMessage;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use App\Services\SupportTicket\SupportQueue;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->push = Mockery::spy(FcmGateway::class);
    $this->app->instance(FcmGateway::class, $this->push);

    foreach (['customer', 'rider', 'admin', 'super_admin'] as $role) {
        Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->agent = User::factory()->create(['first_name' => 'Agnes']);
    $this->agent->assignRole('super_admin');

    $this->category = SupportCategory::query()->create(['name' => 'Payment', 'code' => 'payment', 'is_active' => true]);
    $this->customer = User::factory()->customer()->create();
});

/** Opens a ticket through the app API, as the customer would. */
function customerTicket($test, string $message = 'I was charged twice for my last ride.'): SupportTicket
{
    Sanctum::actingAs($test->customer);

    return SupportTicket::query()->findOrFail(
        $test->postJson('/api/v1/user-app/support-tickets', [
            'category_id' => $test->category->id,
            'subject' => 'Double charge',
            'message' => $message,
        ])->assertCreated()->json('data.id')
    );
}

test('a new ticket needs a reply; replying claims it, moves it to in progress and notifies the customer', function () {
    $ticket = customerTicket($this);
    Event::fake([ChatMessageSent::class]);

    expect(SupportQueue::query('needs_reply')->pluck('id')->all())->toBe([$ticket->id])
        ->and(SupportTickets::getNavigationBadge())->toBe('1');

    $this->travel(1)->minutes(); // the app lists messages newest first
    $this->actingAs($this->agent, 'web');
    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])
        ->set('message', 'Sorry about that, refunding now.')
        ->call('send')
        ->assertHasNoErrors();

    $ticket->refresh();

    expect($ticket->status)->toBe('in_progress')
        ->and($ticket->assigned_to)->toBe($this->agent->id)
        ->and(SupportQueue::query('needs_reply')->count())->toBe(0)
        ->and(SupportQueue::query('mine', $this->agent->id)->pluck('id')->all())->toBe([$ticket->id]);

    Event::assertDispatched(ChatMessageSent::class, fn (ChatMessageSent $event): bool => $event->message->message === 'Sorry about that, refunding now.');
    $this->push->shouldHaveReceived('sendToUser')
        ->withArgs(fn (?User $user, PushMessage $message): bool => $user?->id === $this->customer->id && $message->type === 'support_ticket_reply')
        ->once();

    // The customer sees the reply in the app, and a follow-up puts the ticket back in "needs reply".
    Sanctum::actingAs($this->customer);
    $this->getJson("/api/v1/user-app/support-tickets/{$ticket->id}")
        ->assertOk()
        ->assertJsonPath('data.chat_room.messages.0.message', 'Sorry about that, refunding now.');
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/messages", ['message' => 'Thanks!'])->assertCreated();

    expect(SupportQueue::query('needs_reply')->pluck('id')->all())->toBe([$ticket->id]);
});

test('internal notes stay internal: no broadcast, no push, hidden from the app list and detail', function () {
    $ticket = customerTicket($this);
    Event::fake([ChatMessageSent::class]);
    $this->actingAs($this->agent, 'web');

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])
        ->call('setComposerMode', 'note')
        ->set('message', 'Customer has 3 refunds this month, check with finance.')
        ->call('send')
        ->assertHasNoErrors();

    expect(ChatMessage::query()->where('is_internal_note', true)->count())->toBe(1)
        ->and($ticket->fresh()->status)->toBe('open'); // a note isn't a reply

    Event::assertNotDispatched(ChatMessageSent::class);
    $this->push->shouldNotHaveReceived('sendToUser');

    Sanctum::actingAs($this->customer);
    $this->getJson("/api/v1/user-app/support-tickets/{$ticket->id}")->assertOk()->assertJsonCount(1, 'data.chat_room.messages');
    $this->getJson('/api/v1/user-app/support-tickets')->assertOk()->assertJsonCount(1, 'data.support_tickets.0.chat_room.messages');
});

test('reply & resolve resolves the ticket; nobody can reply until it is reopened', function () {
    $ticket = customerTicket($this);
    $this->actingAs($this->agent, 'web');

    $page = Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])
        ->set('message', 'Refunded, closing this.')
        ->call('send', true);

    expect($ticket->fresh()->status)->toBe('resolved')
        ->and($ticket->fresh()->resolved_at)->not->toBeNull();

    $page->set('message', 'One more thing')->call('send')->assertHasErrors('message');

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/messages", ['message' => 'Still broken'])->assertUnprocessable();

    $this->actingAs($this->agent, 'web');
    $page->call('setStatus', 'open');

    expect($ticket->fresh()->status)->toBe('open')
        ->and($ticket->fresh()->resolved_at)->toBeNull();

    Sanctum::actingAs($this->customer);
    $this->postJson("/api/v1/user-app/support-tickets/{$ticket->id}/messages", ['message' => 'Still broken'])->assertCreated();
});

test('resolving from the status control notifies the customer once', function () {
    $ticket = customerTicket($this);
    $this->actingAs($this->agent, 'web');

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])
        ->call('setStatus', 'resolved')
        ->call('setStatus', 'resolved');

    $this->push->shouldHaveReceived('sendToUser')
        ->withArgs(fn (?User $user, PushMessage $message): bool => $message->type === 'support_ticket_resolved')
        ->once();
});

test('agents can change priority and category, and attach files', function () {
    Storage::fake('public');
    $ticket = customerTicket($this);
    $other = SupportCategory::query()->create(['name' => 'Trip Issue', 'code' => 'trip_issue', 'is_active' => true]);
    $this->actingAs($this->agent, 'web');

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])
        ->call('setPriority', 'urgent')
        ->call('setCategory', $other->id)
        ->set('uploads', [UploadedFile::fake()->image('receipt.png')])
        ->call('send')
        ->assertHasNoErrors();

    $ticket->refresh();
    $reply = ChatMessage::query()->where('sender_id', $this->agent->id)->with('attachments')->firstOrFail();

    expect($ticket->priority)->toBe('urgent')
        ->and($ticket->category_id)->toBe($other->id)
        ->and($reply->message)->toBeNull()
        ->and($reply->attachments)->toHaveCount(1)
        ->and($reply->attachments->first()->type)->toBe('image');

    Storage::disk('public')->assertExists("support-tickets/{$ticket->id}/".basename($reply->attachments->first()->url));
});

test('opening a ticket marks the requester messages as read by the agent', function () {
    $ticket = customerTicket($this);
    $this->actingAs($this->agent, 'web');

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id]);

    expect(ChatMessage::query()->where('sender_id', $this->customer->id)->first()->read_by)->toContain($this->agent->id);
});

test('super admin can open the queue and a ticket', function () {
    $ticket = customerTicket($this);
    $this->actingAs($this->agent, 'web');

    $this->get(SupportTickets::getUrl())->assertOk()->assertSee('Needs reply');
    $this->get(SupportTicketDetails::getUrl(['record' => $ticket->id]))
        ->assertOk()
        ->assertSee('Double charge')
        ->assertSee('I was charged twice for my last ride.')
        ->assertSee('Send reply');
});

test('view-only staff can read but not reply or change the ticket', function () {
    $ticket = customerTicket($this);
    Role::findByName('admin', 'web')->givePermissionTo('View:SupportTickets');
    $this->actingAs(User::factory()->create()->assignRole('admin'), 'web');

    $this->get(SupportTicketDetails::getUrl(['record' => $ticket->id]))
        ->assertOk()
        ->assertDontSee('Send reply')
        ->assertSee('Respond to support tickets');

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])
        ->set('message', 'hi')
        ->call('send')
        ->assertForbidden();

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])
        ->call('setStatus', 'closed')
        ->assertForbidden();

    expect($ticket->fresh()->status)->toBe('open');
});

test('only staff with Assign can hand a ticket to someone else; responders can take it themselves', function () {
    $ticket = customerTicket($this);
    $responder = User::factory()->create();
    $responder->assignRole('admin');
    Role::findByName('admin', 'web')->givePermissionTo(['View:SupportTickets', 'Respond:SupportTicket']);
    $this->actingAs($responder, 'web');

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])
        ->call('assignTo', (string) $this->agent->id)
        ->assertForbidden();

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])->call('assignToMe');
    expect($ticket->fresh()->assigned_to)->toBe($responder->id);

    $this->actingAs($this->agent, 'web');
    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])->call('assignTo', (string) $this->agent->id);
    expect($ticket->fresh()->assigned_to)->toBe($this->agent->id)
        ->and(SupportTicketDetails::agentOptions())->toHaveKeys([$this->agent->id, $responder->id]);

    Livewire::test(SupportTicketDetails::class, ['record' => $ticket->id])->call('assignTo', '');
    expect($ticket->fresh()->assigned_to)->toBeNull();
});

test('staff without View:SupportTickets cannot open the pages', function () {
    $ticket = customerTicket($this);
    $this->actingAs(User::factory()->create()->assignRole('admin'), 'web');

    $this->get(SupportTickets::getUrl())->assertForbidden();
    $this->get(SupportTicketDetails::getUrl(['record' => $ticket->id]))->assertForbidden();
});
