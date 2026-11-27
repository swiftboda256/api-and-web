<?php

use App\Filament\Resources\Communications\Pages\CreateCommunication;
use App\Filament\Resources\Communications\Pages\ViewCommunication;
use App\Filament\Resources\Communications\RelationManagers\DeliveriesRelationManager;
use App\Models\Communication;
use App\Models\CommunicationDelivery;
use App\Models\Configuration;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\CommunicationMailNotification;
use App\Notifications\PushEventNotification;
use App\Services\Communication\CommunicationService;
use App\Services\Communication\SmsCostNotAcknowledged;
use App\Services\Communication\SmsSegments;
use App\Services\Push\ApnsVoipGateway;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use App\Services\Sms\Contracts\SmsGateway;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Kreait\Firebase\Contract\Messaging;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

/**
 * Admin Communications: audience, reach/cost preview, the delivery log (sent / failed with
 * reason per recipient and channel), and the compose page's SMS cost acknowledgement.
 */
beforeEach(function () {
    foreach (['customer', 'rider', 'admin', 'super_admin'] as $role) {
        Role::query()->firstOrCreate(['name' => $role, 'guard_name' => 'web']);
    }

    Notification::fake();

    // Pushes go to whoever has an active device; records what was sent.
    $this->push = new class(Mockery::mock(Messaging::class), new ApnsVoipGateway) extends FcmGateway
    {
        /** @var list<array{0: int, 1: PushMessage}> */
        public array $sent = [];

        public ?string $failWith = null;

        public function deliverNow(User $user, PushMessage $message): ?string
        {
            $this->sent[] = [$user->id, $message];

            return $this->failWith;
        }
    };
    $this->app->instance(FcmGateway::class, $this->push);

    $this->sms = new class implements SmsGateway
    {
        /** @var list<array{0: string, 1: string}> */
        public array $sent = [];

        /** @var array<string, string> phone => failure */
        public array $failures = [];

        public function send(string $to, string $message, ?string $senderId = null): bool
        {
            return $this->deliver($to, $message, $senderId) === null;
        }

        public function deliver(string $to, string $message, ?string $senderId = null): ?string
        {
            $this->sent[] = [$to, $message];

            return $this->failures[$to] ?? null;
        }
    };
    $this->app->instance(SmsGateway::class, $this->sms);
});

function withDevice(User $user, ?string $token = 'fcm-token'): User
{
    UserDevice::query()->create([
        'user_id' => $user->id,
        'device_type' => 'android',
        'fcm_token' => $token ?? '',
        'active' => true,
        'last_seen_at' => now(),
    ]);

    return $user;
}

it('targets active users in the roles plus hand-picked users', function () {
    $customer = User::factory()->customer()->create();
    $suspendedCustomer = User::factory()->customer()->create(['status' => 'suspended']);
    $rider = User::factory()->rider()->create();
    $pickedSuspended = User::factory()->create(['status' => 'suspended']);
    $deleted = User::factory()->create(['status' => 'deleted']);

    $ids = app(CommunicationService::class)
        ->audience(['customer'], [$pickedSuspended->id, $deleted->id])
        ->pluck('id')
        ->all();

    expect($ids)->toContain($customer->id, $pickedSuspended->id)
        ->not->toContain($suspendedCustomer->id, $rider->id, $deleted->id);

    expect(app(CommunicationService::class)->audience([], [])->count())->toBe(0);
});

it('previews reach per channel and the SMS cost', function () {
    withDevice(User::factory()->customer()->create(['email' => 'a@example.com', 'phone' => '256700000001']));
    User::factory()->customer()->create(['email' => null, 'phone' => '256700000002']);
    withDevice(User::factory()->customer()->create(['email' => 'c@example.com', 'phone' => '256700000003']), null);

    $preview = app(CommunicationService::class)->preview(['customer'], [], ['push', 'email', 'sms'], str_repeat('a', 200));

    expect($preview['recipients'])->toBe(3)
        ->and($preview['reachable'])->toBe(['push' => 1, 'email' => 2, 'sms' => 3])
        ->and($preview['sms_segments'])->toBe(2)
        ->and($preview['sms_cost'])->toBe(3 * 2 * 35);
});

it('counts SMS parts the way the network bills them', function () {
    expect(SmsSegments::count(''))->toBe(0)
        ->and(SmsSegments::count(str_repeat('a', 160)))->toBe(1)
        ->and(SmsSegments::count(str_repeat('a', 161)))->toBe(2)
        ->and(SmsSegments::count(str_repeat('a', 306)))->toBe(2)
        ->and(SmsSegments::count(str_repeat('a', 307)))->toBe(3)
        ->and(SmsSegments::count(str_repeat('€', 80)))->toBe(1) // 2 units each
        ->and(SmsSegments::count(str_repeat('€', 81)))->toBe(2)
        ->and(SmsSegments::count('Hi 👋'))->toBe(1)
        ->and(SmsSegments::count(str_repeat('a', 70).'👋'))->toBe(2)
        ->and(SmsSegments::isUnicode('Hello'))->toBeFalse()
        ->and(SmsSegments::isUnicode('Hello 👋'))->toBeTrue();
});

it('sends on every channel and logs unreachable recipients as failed with the reason', function () {
    $reachable = withDevice(User::factory()->customer()->create(['first_name' => 'Amina', 'email' => 'amina@example.com', 'phone' => '256700000001']));
    $bare = User::factory()->customer()->create(['email' => null, 'phone' => '']);
    $badEmail = User::factory()->customer()->create(['email' => 'not-an-email', 'phone' => '256700000003']);

    $communication = app(CommunicationService::class)->send([
        'title' => 'Fares are down',
        'body' => 'Rides are 10% off this weekend.',
        'channels' => ['push', 'email', 'sms'],
        'target_roles' => ['customer'],
        'sms_cost_acknowledged_amount' => 2 * 35, // two have a phone number
    ]);

    $communication->refresh();
    expect($communication->status)->toBe(Communication::STATUS_COMPLETED)
        ->and($communication->recipients_count)->toBe(3)
        ->and($communication->completed_at)->not->toBeNull()
        ->and($communication->deliveries()->count())->toBe(9);

    $log = fn (User $user, string $channel): CommunicationDelivery => $communication->deliveries()
        ->where('user_id', $user->id)->where('channel', $channel)->firstOrFail();

    foreach (['push', 'email', 'sms'] as $channel) {
        expect($log($reachable, $channel)->status)->toBe('sent')
            ->and($log($reachable, $channel)->sent_at)->not->toBeNull();
    }
    expect($log($reachable, 'push')->destination)->toBe('Android device')
        ->and($log($reachable, 'email')->destination)->toBe('amina@example.com')
        ->and($log($reachable, 'sms')->destination)->toBe('256700000001');

    expect($log($bare, 'push')->only(['status', 'failure_reason']))->toBe(['status' => 'failed', 'failure_reason' => CommunicationService::NO_PUSH_DEVICE])
        ->and($log($bare, 'email')->failure_reason)->toBe(CommunicationService::NO_EMAIL)
        ->and($log($bare, 'sms')->failure_reason)->toBe(CommunicationService::NO_PHONE)
        ->and($log($badEmail, 'email')->failure_reason)->toBe(CommunicationService::INVALID_EMAIL)
        ->and($log($badEmail, 'sms')->status)->toBe('sent');

    // Only reachable recipients were actually contacted; the SMS carries the body only.
    expect($this->push->sent)->toHaveCount(1)
        ->and($this->push->sent[0][0])->toBe($reachable->id)
        ->and($this->push->sent[0][1]->type)->toBe(CommunicationService::PUSH_TYPE)
        ->and($this->push->sent[0][1]->title)->toBe('Fares are down')
        ->and($this->sms->sent)->toBe([
            ['256700000001', 'Rides are 10% off this weekend.'],
            ['256700000003', 'Rides are 10% off this weekend.'],
        ]);

    Notification::assertSentTo($reachable, PushEventNotification::class);
    Notification::assertSentOnDemand(
        CommunicationMailNotification::class,
        fn ($notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === 'amina@example.com',
    );
    Notification::assertSentOnDemandTimes(CommunicationMailNotification::class, 1);
});

it('logs the gateway reason when a provider rejects a message', function () {
    $user = withDevice(User::factory()->customer()->create(['phone' => '256700000009']));
    $this->sms->failures['256700000009'] = 'EgoSMS: Insufficient balance';
    $this->push->failWith = 'FCM token no longer registered (app uninstalled or logged out)';

    $communication = app(CommunicationService::class)->send([
        'title' => 'Hello',
        'body' => 'Hi there',
        'channels' => ['push', 'sms'],
        'target_user_ids' => [$user->id],
        'sms_cost_acknowledged_amount' => 35,
    ]);

    expect($communication->deliveries()->where('channel', 'sms')->value('failure_reason'))->toBe('EgoSMS: Insufficient balance')
        ->and($communication->deliveries()->where('channel', 'push')->value('failure_reason'))->toBe('FCM token no longer registered (app uninstalled or logged out)')
        ->and($communication->deliveries()->where('status', 'sent')->count())->toBe(0)
        ->and($communication->fresh()->status)->toBe(Communication::STATUS_COMPLETED);
});

it('stores the SMS cost estimate and who acknowledged it, and when', function () {
    User::factory()->customer()->count(4)->create();
    $admin = User::factory()->create();

    $communication = app(CommunicationService::class)->send([
        'title' => 'Hello',
        'body' => str_repeat('a', 200),
        'channels' => ['sms'],
        'target_roles' => ['customer'],
        'sms_cost_acknowledged_amount' => 4 * 2 * 35,
    ], $admin);

    expect($communication->only(['sms_segments', 'sms_unit_cost', 'sms_estimated_cost', 'sms_cost_acknowledged_by', 'sms_cost_acknowledged_amount']))
        ->toBe([
            'sms_segments' => 2,
            'sms_unit_cost' => 35,
            'sms_estimated_cost' => 4 * 2 * 35,
            'sms_cost_acknowledged_by' => $admin->id,
            'sms_cost_acknowledged_amount' => 4 * 2 * 35,
        ])
        ->and($communication->sms_cost_acknowledged_at)->not->toBeNull()
        ->and($communication->smsCostAcknowledger->is($admin))->toBeTrue();
});

it('refuses SMS that was not acknowledged, or acknowledged at a cost that has since changed', function (?int $acknowledged) {
    User::factory()->customer()->count(2)->create(); // costs 2 × 35 = 70

    expect(fn () => app(CommunicationService::class)->send([
        'title' => 'Hello',
        'body' => 'Hi',
        'channels' => ['sms'],
        'target_roles' => ['customer'],
        'sms_cost_acknowledged_amount' => $acknowledged,
    ]))->toThrow(SmsCostNotAcknowledged::class);

    expect(Communication::query()->count())->toBe(0)
        ->and($this->sms->sent)->toBe([]);
})->with([
    'not acknowledged' => [null],
    'acknowledged before more recipients were added' => [35],
]);

it('records no acknowledgement when no SMS is sent', function () {
    $user = User::factory()->customer()->create(['email' => 'a@example.com']);

    $communication = app(CommunicationService::class)->send([
        'title' => 'Hello', 'body' => 'Hi', 'channels' => ['email'], 'target_user_ids' => [$user->id],
        'sms_cost_acknowledged_amount' => 999,
    ]);

    expect($communication->only(['sms_cost_acknowledged_by', 'sms_cost_acknowledged_at', 'sms_cost_acknowledged_amount']))
        ->toBe(['sms_cost_acknowledged_by' => null, 'sms_cost_acknowledged_at' => null, 'sms_cost_acknowledged_amount' => null]);
});

it('prices SMS from the sms_cost_per_sms configuration', function () {
    User::factory()->customer()->count(2)->create();
    $service = app(CommunicationService::class);

    expect($service->smsUnitCost())->toBe(CommunicationService::DEFAULT_SMS_COST);

    Configuration::query()->create(['key' => 'sms_cost_per_sms', 'value' => 50, 'value_type' => 'number', 'group' => 'communications']);

    expect($service->smsUnitCost())->toBe(50)
        ->and($service->preview(['customer'], [], ['sms'], 'Hi')['sms_cost'])->toBe(2 * 1 * 50);

    Configuration::query()->where('key', 'sms_cost_per_sms')->sole()->update(['value' => 'abc']);

    expect($service->smsUnitCost())->toBe(CommunicationService::DEFAULT_SMS_COST);
});

describe('admin page', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super_admin');
        $this->actingAs($this->admin);
    });

    it('requires the SMS cost to be acknowledged before sending SMS', function () {
        $customer = User::factory()->customer()->create(['phone' => '256700000001']);

        Livewire::test(CreateCommunication::class)
            ->fillForm([
                'target_roles' => ['customer'],
                'channels' => ['sms'],
                'title' => 'Hello',
                'body' => 'Hi there',
            ])
            ->call('create')
            ->assertHasFormErrors(['confirm_sms_cost' => 'accepted']);

        expect(Communication::query()->count())->toBe(0);

        Livewire::test(CreateCommunication::class)
            ->fillForm([
                'target_roles' => ['customer'],
                'channels' => ['sms'],
                'title' => 'Hello',
                'body' => 'Hi there',
                'confirm_sms_cost' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $communication = Communication::query()->sole();
        expect($communication->created_by)->toBe($this->admin->id)
            ->and($communication->sms_cost_acknowledged_by)->toBe($this->admin->id)
            ->and($communication->sms_cost_acknowledged_amount)->toBe(35)
            ->and($communication->sms_cost_acknowledged_at)->not->toBeNull()
            ->and($communication->deliveries()->where('user_id', $customer->id)->value('status'))->toBe('sent');
    });

    it('makes the sender confirm again when the SMS cost changed after they ticked the box', function () {
        User::factory()->customer()->create(['phone' => '256700000001']);

        $page = Livewire::test(CreateCommunication::class)
            ->fillForm([
                'target_roles' => ['customer'],
                'channels' => ['sms'],
                'title' => 'Hello',
                'body' => 'Hi there',
                'confirm_sms_cost' => true,
            ])
            ->assertFormSet(['sms_cost_acknowledged_amount' => 35]);

        // Someone else signs up before Send is pressed: the cost is now 70.
        User::factory()->customer()->create(['phone' => '256700000002']);

        $page->call('create')
            ->assertHasFormErrors(['confirm_sms_cost'])
            ->assertFormSet(['confirm_sms_cost' => false, 'sms_cost_acknowledged_amount' => null]);

        expect(Communication::query()->count())->toBe(0);

        $page->fillForm(['confirm_sms_cost' => true])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Communication::query()->sole()->sms_cost_acknowledged_amount)->toBe(70);
    });

    it('needs a role or a user, and no cost acknowledgement without SMS', function () {
        Livewire::test(CreateCommunication::class)
            ->fillForm(['channels' => ['email'], 'title' => 'Hello', 'body' => 'Hi'])
            ->call('create')
            ->assertHasFormErrors(['target_roles', 'target_user_ids']);

        $user = User::factory()->create(['email' => 'x@example.com']);

        Livewire::test(CreateCommunication::class)
            ->fillForm(['target_user_ids' => [$user->id], 'channels' => ['email'], 'title' => 'Hello', 'body' => 'Hi'])
            ->call('create')
            ->assertHasNoFormErrors();

        expect(Communication::query()->sole()->target_user_ids)->toBe([$user->id]);
    });

    it('shows the delivery log', function () {
        $user = User::factory()->customer()->create(['email' => null]);
        $communication = app(CommunicationService::class)->send([
            'title' => 'Hello', 'body' => 'Hi', 'channels' => ['email'], 'target_user_ids' => [$user->id],
        ]);

        // The log is embedded in the page (under the message), not a separate relation manager.
        Livewire::test(ViewCommunication::class, ['record' => $communication->getKey()])
            ->assertOk()
            ->assertSee('Hello')
            ->assertSeeLivewire(DeliveriesRelationManager::class);

        Livewire::test(DeliveriesRelationManager::class, ['ownerRecord' => $communication, 'pageClass' => ViewCommunication::class])
            ->assertOk()
            ->assertSee(CommunicationService::NO_EMAIL)
            ->assertCanSeeTableRecords($communication->deliveries);
    });

    it('is hidden from users without the permission', function () {
        $agent = User::factory()->create();
        $agent->assignRole('admin');
        $this->actingAs($agent);

        Livewire::test(CreateCommunication::class)->assertForbidden();
    });
});
