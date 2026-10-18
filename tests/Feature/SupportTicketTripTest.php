<?php

use App\Models\DeliveryDetails;
use App\Models\SupportCategory;
use App\Models\SupportTicket;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * A support ticket can be about a trip the user drove, or one they have a ride or
 * delivery booking on -- the customer is on the booking now, not on the trip.
 */
beforeEach(function () {
    $this->category = SupportCategory::query()->create(['name' => 'Trip issue', 'code' => 'trip_issue', 'is_active' => true]);
    $this->trip = Trip::factory()->create(['type' => 'ride', 'customer_id' => null, 'rider_id' => User::factory()->rider()->create()->id]);
});

function ticketFor($test, User $user, string $app, int $tripId)
{
    Sanctum::actingAs($user);

    return $test->postJson("/api/v1/{$app}/support-tickets", [
        'category_id' => $test->category->id,
        'subject' => 'Driver took a long route',
        'message' => 'The trip took much longer than expected.',
        'trip_id' => $tripId,
    ]);
}

test('a passenger can raise a ticket about a trip they have a booking on', function () {
    $passenger = User::factory()->customer()->create();
    TripPassenger::query()->create(['trip_id' => $this->trip->id, 'customer_id' => $passenger->id, 'seats_requested' => 1, 'status' => 'dropped_off', 'requested_at' => now()]);

    ticketFor($this, $passenger, 'user-app', $this->trip->id)->assertCreated();

    expect(SupportTicket::query()->where('user_id', $passenger->id)->value('trip_id'))->toBe($this->trip->id);
});

test('a sender can raise a ticket about their delivery trip', function () {
    $sender = User::factory()->customer()->create();
    $trip = Trip::factory()->delivery()->create(['customer_id' => null]);
    DeliveryDetails::factory()->for($trip)->create(['sender_id' => $sender->id]);

    ticketFor($this, $sender, 'user-app', $trip->id)->assertCreated();
});

test('the rider can raise a ticket about a trip they drove', function () {
    ticketFor($this, User::query()->findOrFail($this->trip->rider_id), 'rider-app', $this->trip->id)->assertCreated();
});

test('nobody else can attach the trip to a ticket', function () {
    // Someone with a booking on a different trip.
    $other = User::factory()->customer()->create();
    $otherTrip = Trip::factory()->create(['type' => 'ride', 'customer_id' => null]);
    TripPassenger::query()->create(['trip_id' => $otherTrip->id, 'customer_id' => $other->id, 'seats_requested' => 1, 'status' => 'dropped_off', 'requested_at' => now()]);

    ticketFor($this, $other, 'user-app', $this->trip->id)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('trip_id');
});
