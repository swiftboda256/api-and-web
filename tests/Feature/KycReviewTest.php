<?php

use App\Filament\Pages\Kyc\KycReview;
use App\Filament\Pages\Kyc\KycReviews;
use App\Filament\Pages\Riders\RiderDetails;
use App\Models\Document;
use App\Models\RiderProfile;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use App\Services\Rider\KycQueue;
use Database\Seeders\PermissionSeeder;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->push = Mockery::spy(FcmGateway::class);
    $this->app->instance(FcmGateway::class, $this->push);

    foreach (['customer', 'rider', 'admin', 'super_admin'] as $role) {
        Role::query()->create(['name' => $role, 'guard_name' => 'web']);
    }

    $this->seed(PermissionSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->superAdmin = User::factory()->create();
    $this->superAdmin->assignRole('super_admin');
});

/**
 * A rider with the given document statuses (null = not uploaded) and vehicle status (null = no vehicle).
 */
function kycRider(?string $nationalId, ?string $license, ?string $vehicle, string $kycStatus = 'pending'): User
{
    $user = User::factory()->rider()->create();
    $profile = RiderProfile::factory()->create(['user_id' => $user->id, 'kyc_status' => $kycStatus]);

    foreach (['national_id' => $nationalId, 'driving_license' => $license] as $type => $status) {
        if ($status !== null) {
            Document::query()->create([
                'documentable_type' => RiderProfile::class,
                'documentable_id' => $profile->id,
                'document_type' => $type,
                'file_path' => "documents/riders/{$profile->id}/{$type}.png",
                'status' => $status,
            ]);
        }
    }

    if ($vehicle !== null) {
        Vehicle::factory()->create(['rider_profile_id' => $profile->id, 'status' => $vehicle]);
    }

    return $user;
}

test('each rider lands in exactly one queue tab', function () {
    $awaitingDocument = kycRider('pending', 'approved', 'approved');
    $awaitingVehicle = kycRider('approved', 'approved', 'pending', 'approved');
    $incomplete = kycRider('approved', null, null);
    $rejected = kycRider('rejected', 'approved', null, 'rejected');
    $approved = kycRider('approved', 'approved', 'approved', 'approved');

    $ids = fn (string $tab): array => KycQueue::query($tab)->orderBy('users.id')->pluck('users.id')->all();

    expect($ids('awaiting'))->toBe([$awaitingDocument->id, $awaitingVehicle->id])
        ->and($ids('incomplete'))->toBe([$incomplete->id])
        ->and($ids('rejected'))->toBe([$rejected->id]) // missing vehicle too, but the rejection comes first
        ->and($ids('approved'))->toBe([$approved->id])
        ->and(KycQueue::awaitingCount())->toBe(2)
        ->and(KycReviews::getNavigationBadge())->toBe('2');
});

test('super admin can open the queue and a review', function () {
    $rider = kycRider('pending', 'pending', 'pending');
    kycRider('pending', 'approved', 'approved'); // someone else waiting, so "Next in queue" shows

    $this->actingAs($this->superAdmin);

    $this->get(KycReviews::getUrl())->assertOk();
    $this->get(KycReview::getUrl(['record' => $rider->id]))
        ->assertOk()
        ->assertSee('Next in queue', escape: false)
        ->assertSee('Open rider profile');
});

test('approving both documents approves the rider', function () {
    $rider = kycRider('pending', 'pending', 'approved');
    $this->actingAs($this->superAdmin);

    $component = Livewire::test(KycReview::class, ['record' => $rider->id]);

    foreach ($rider->riderProfile->documents as $document) {
        $component->call('approveDocument', $document->id);
    }

    expect($rider->riderProfile->fresh()->kyc_status)->toBe('approved')
        ->and(KycQueue::query('approved')->pluck('users.id')->all())->toBe([$rider->id]);
});

test('rejecting the vehicle saves the reason and notifies the rider', function () {
    $rider = kycRider('approved', 'approved', 'pending', 'approved');
    $this->actingAs($this->superAdmin);

    Livewire::test(KycReview::class, ['record' => $rider->id])
        ->call('rejectVehicle')
        ->set('rejectionReason', 'The insurance has expired.')
        ->call('confirmReject')
        ->assertHasNoErrors();

    $vehicle = $rider->riderProfile->vehicle->fresh();

    expect($vehicle->status)->toBe('rejected')
        ->and($vehicle->rejection_reason)->toBe('The insurance has expired.');

    $this->push->shouldHaveReceived('sendToUser')
        ->withArgs(fn (?User $user, PushMessage $message): bool => $user?->id === $rider->id && $message->type === 'vehicle_rejected')
        ->once();
});

test('a reason is required to reject', function () {
    $rider = kycRider('pending', 'pending', 'pending');
    $this->actingAs($this->superAdmin);

    Livewire::test(KycReview::class, ['record' => $rider->id])
        ->call('rejectDocument', $rider->riderProfile->documents->first()->id)
        ->call('confirmReject')
        ->assertHasErrors(['rejectionReason' => 'required']);
});

test('an admin who can view but not review cannot approve or reject', function () {
    $rider = kycRider('pending', 'pending', 'pending');
    Role::findByName('admin')->givePermissionTo('View:KycReviews');
    $this->actingAs(User::factory()->create()->assignRole('admin'));

    $this->get(KycReview::getUrl(['record' => $rider->id]))
        ->assertOk()
        ->assertSee('Review KYC')
        ->assertDontSee('wire:click="approveDocument', escape: false);

    Livewire::test(KycReview::class, ['record' => $rider->id])
        ->call('approveDocument', $rider->riderProfile->documents->first()->id)
        ->assertForbidden();

    expect($rider->riderProfile->documents()->where('status', 'approved')->count())->toBe(0);
});

test('an admin without View:KycReviews cannot open the pages', function () {
    $rider = kycRider('pending', 'pending', 'pending');
    $this->actingAs(User::factory()->create()->assignRole('admin'));

    $this->get(KycReviews::getUrl())->assertForbidden();
    $this->get(KycReview::getUrl(['record' => $rider->id]))->assertForbidden();
});

test('previous and next follow the queue order, and skipping reaches the end', function () {
    $this->travelTo(now()->subDays(3));
    $first = kycRider('pending', 'pending', 'pending');
    $this->travelBack();
    $this->travelTo(now()->subDays(2));
    $second = kycRider('pending', 'approved', 'approved');
    $this->travelBack();
    $this->travelTo(now()->subDay());
    $third = kycRider('approved', 'approved', 'pending', 'approved');
    $this->travelBack();
    kycRider('approved', 'approved', 'approved', 'approved'); // not waiting

    $this->actingAs($this->superAdmin);
    $nav = fn (User $rider): array => Livewire::test(KycReview::class, ['record' => $rider->id])->instance()->queueNavigation;
    $url = fn (User $rider): string => KycReview::getUrl(['record' => $rider->id]);

    expect($nav($first))->toBe(['position' => 1, 'total' => 3, 'previous' => null, 'next' => $url($second)])
        ->and($nav($second))->toBe(['position' => 2, 'total' => 3, 'previous' => $url($first), 'next' => $url($third)])
        ->and($nav($third))->toBe(['position' => 3, 'total' => 3, 'previous' => $url($second), 'next' => null]);
});

test('after a decision the rider leaves the queue and next goes to the longest-waiting rider', function () {
    $this->travelTo(now()->subDays(2));
    $waiting = kycRider('pending', 'approved', 'approved');
    $this->travelBack();
    $reviewed = kycRider('approved', 'approved', 'pending', 'approved');

    $this->actingAs($this->superAdmin);

    $component = Livewire::test(KycReview::class, ['record' => $reviewed->id])->call('approveVehicle');

    expect($component->instance()->queueNavigation)
        ->toBe(['position' => null, 'total' => 1, 'previous' => null, 'next' => KycReview::getUrl(['record' => $waiting->id])]);
});

test('the rider details KYC and vehicle tabs still work through the shared actions', function () {
    $rider = kycRider('pending', 'pending', 'pending');
    $this->actingAs($this->superAdmin);

    $this->get(RiderDetails::getUrl(['record' => $rider->id]))
        ->assertOk()
        ->assertSee('Open in KYC review');
    $this->get(RiderDetails::getUrl(['record' => $rider->id, 'tab' => 'vehicle']))
        ->assertOk()
        ->assertSee('Vehicle photos');

    Livewire::test(RiderDetails::class, ['record' => $rider->id])
        ->call('approveVehicle')
        ->call('approveDocument', $rider->riderProfile->documents->first()->id);

    expect($rider->riderProfile->vehicle->fresh()->status)->toBe('approved')
        ->and($rider->riderProfile->documents()->where('status', 'approved')->count())->toBe(1);
});
