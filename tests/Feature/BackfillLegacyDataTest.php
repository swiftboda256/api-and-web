<?php

use App\Models\DeliveryDetails;
use App\Models\PricingRule;
use App\Models\PromoCode;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Services\Push\FcmGateway;
use Illuminate\Support\Facades\DB;

/**
 * trips:backfill-passenger-model against data shaped the way main writes it: a trip-level
 * fare breakdown at booking, promo codes on the trip, and transactions linked to the trip.
 */

/**
 * The trip-level breakdown row main writes at booking, with the components charged.
 *
 * @param  array<string, mixed>  $overrides
 */
function legacyBreakdown(Trip $trip, array $overrides = []): void
{
    DB::table('trip_fare_breakdowns')->insert([
        'trip_id' => $trip->id,
        'base_fare' => 1234,
        'distance_fare' => 5678,
        'time_fare' => 321,
        'surge_multiplier' => 1.5,
        'surge_amount' => 999,
        'discount_amount' => 1000,
        'cancellation_fee' => 0,
        'commission_rate' => 9,
        'commission_amount' => 810,
        'rider_earning' => 8190,
        'total' => 9000,
        'currency_code' => 'UGX',
        'created_at' => now(),
        'updated_at' => now(),
        ...$overrides,
    ]);
}

function tripTransaction(Trip $trip, string $type): Transaction
{
    return Transaction::query()->create([
        'user_id' => $trip->customer_id,
        'method' => 'mobile_money',
        'direction' => 'debit',
        'transaction_type' => $type,
        'amount' => 9000,
        'currency_code' => 'UGX',
        'reference_type' => Trip::class,
        'reference_id' => $trip->id,
        'status' => 'completed',
    ]);
}

function promoCode(): PromoCode
{
    return PromoCode::query()->create(['code' => 'SAVE1000', 'discount_type' => 'fixed', 'discount_value' => 1000]);
}

test('a ride keeps the fare components recorded at booking', function () {
    // 1234 + 5678 + 321 + 999 - 1000 = 7232, rounded down to 7000.
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'completed', 'estimated_fare' => 7000, 'final_fare' => 7000]);
    PricingRule::factory()->create(['zone_id' => $trip->zone_id, 'vehicle_type_id' => $trip->vehicle_type_id]);
    legacyBreakdown($trip, ['total' => 7000]);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $breakdown = TripPassenger::query()->where('trip_id', $trip->id)->firstOrFail()->fareBreakdown;

    expect(TripFareBreakdown::query()->where('trip_id', $trip->id)->count())->toBe(1)
        ->and((float) $breakdown->base_fare)->toBe(1234.0)
        ->and((float) $breakdown->distance_fare)->toBe(5678.0)
        ->and((float) $breakdown->time_fare)->toBe(321.0)
        ->and((float) $breakdown->surge_multiplier)->toBe(1.5)
        ->and((float) $breakdown->surge_amount)->toBe(999.0)
        ->and((float) $breakdown->discount_amount)->toBe(1000.0)
        ->and((float) $breakdown->commission_amount)->toBe(810.0)
        ->and((float) $breakdown->rider_earning)->toBe(8190.0)
        ->and((float) $breakdown->estimated_fare_before_rounding)->toBe(7232.0)
        ->and((float) $breakdown->final_fare_before_rounding)->toBe(7232.0);
});

test('a pre-rounding total is never below the fare charged', function () {
    // Components that add up to less than the fare actually charged (seen in production).
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'cancelled', 'estimated_fare' => 12500, 'final_fare' => null]);
    legacyBreakdown($trip, ['base_fare' => 1000, 'distance_fare' => 9360, 'time_fare' => 0, 'surge_amount' => 0, 'discount_amount' => 0, 'total' => 12500]);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $breakdown = TripPassenger::query()->where('trip_id', $trip->id)->firstOrFail()->fareBreakdown;

    expect((float) $breakdown->estimated_fare_before_rounding)->toBe(12500.0)
        ->and((float) $breakdown->distance_fare)->toBe(9360.0);
});

test('a ride with no recorded breakdown gets recalculated components and the trip\'s discount', function () {
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'requested', 'final_fare' => null, 'discount_amount' => 500]);
    PricingRule::factory()->create(['zone_id' => $trip->zone_id, 'vehicle_type_id' => $trip->vehicle_type_id]);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $breakdown = TripPassenger::query()->where('trip_id', $trip->id)->firstOrFail()->fareBreakdown;

    expect($breakdown->base_fare)->not->toBeNull()
        ->and((float) $breakdown->discount_amount)->toBe(500.0)
        ->and($breakdown->estimated_fare_before_rounding)->not->toBeNull()
        ->and($breakdown->final_fare_before_rounding)->toBeNull();
});

test('a ride\'s promo code moves onto its passenger booking', function () {
    $promo = promoCode();
    $trip = Trip::factory()->create(['type' => 'ride', 'promo_code_id' => $promo->id]);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    expect(TripPassenger::query()->where('trip_id', $trip->id)->value('promo_code_id'))->toBe($promo->id);
});

test('a delivery gets its base fare, promo code and discount', function () {
    $promo = promoCode();
    $trip = Trip::factory()->delivery()->create(['status' => 'completed', 'promo_code_id' => $promo->id, 'discount_amount' => 700]);
    legacyBreakdown($trip, ['base_fare' => 2000]);
    $delivery = DeliveryDetails::factory()->for($trip)->create();

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    $delivery->refresh();

    expect((float) $delivery->base_fare_amount)->toBe(2000.0)
        ->and($delivery->promo_code_id)->toBe($promo->id)
        ->and((float) $delivery->discount_amount)->toBe(700.0)
        // The trip-level row stays as the record of the commission and rider earning.
        ->and(TripFareBreakdown::query()->where('trip_id', $trip->id)->whereNull('passenger_id')->count())->toBe(1);
});

test('transactions linked to a legacy trip are re-pointed at its booking', function () {
    $ride = Trip::factory()->create(['type' => 'ride']);
    $delivery = Trip::factory()->delivery()->create();
    DeliveryDetails::factory()->for($delivery)->create();

    $payment = tripTransaction($ride, 'trip_payment');
    $payout = tripTransaction($ride, 'trip_payout');
    $deliveryPayment = tripTransaction($delivery, 'trip_payment');

    $this->artisan('trips:backfill-passenger-model')
        ->expectsOutputToContain('Re-pointed 3 trip transaction(s) to their booking.')
        ->assertExitCode(0);

    $passenger = TripPassenger::query()->where('trip_id', $ride->id)->firstOrFail();
    $deliveryDetails = DeliveryDetails::query()->where('trip_id', $delivery->id)->firstOrFail();

    foreach ([$payment, $payout] as $transaction) {
        expect($transaction->fresh()->reference_type)->toBe(TripPassenger::class)
            ->and($transaction->fresh()->reference_id)->toBe($passenger->id);
    }

    expect($deliveryPayment->fresh()->reference_type)->toBe(DeliveryDetails::class)
        ->and($deliveryPayment->fresh()->reference_id)->toBe($deliveryDetails->id);

    // Running again changes nothing.
    $this->artisan('trips:backfill-passenger-model')
        ->expectsOutputToContain('Re-pointed 0 trip transaction(s)')
        ->assertExitCode(0);
});

test('a dry run counts the transactions it would re-point without changing them', function () {
    $ride = Trip::factory()->create(['type' => 'ride']);
    $payment = tripTransaction($ride, 'trip_payment');

    $this->artisan('trips:backfill-passenger-model', ['--dry-run' => true])
        ->expectsOutputToContain('Would re-point 1 trip transaction(s) to their booking.')
        ->assertExitCode(0);

    expect($payment->fresh()->reference_type)->toBe(Trip::class);
});

test('transactions of a delivery trip with no delivery booking are left on the trip and reported', function () {
    $delivery = Trip::factory()->delivery()->create();
    $payment = tripTransaction($delivery, 'trip_payment');

    $this->artisan('trips:backfill-passenger-model')
        ->expectsOutputToContain('1 trip transaction(s) left linked to their trip')
        ->assertExitCode(0);

    expect($payment->fresh()->reference_type)->toBe(Trip::class);
});

test('a trip still searching within the timeout joins the new dispatch from when it was requested', function () {
    $requestedAt = now()->subMinutes(2)->startOfSecond();
    $searching = Trip::factory()->create(['type' => 'ride', 'status' => 'searching', 'requested_at' => $requestedAt, 'completed_at' => null]);
    $completed = Trip::factory()->create(['type' => 'ride', 'status' => 'completed']);

    $this->artisan('trips:backfill-passenger-model')->assertExitCode(0);

    expect($searching->fresh()->search_started_at->equalTo($requestedAt))->toBeTrue()
        ->and($searching->fresh()->status)->toBe('searching')
        ->and(TripPassenger::query()->where('trip_id', $searching->id)->value('status'))->toBe('requested')
        ->and($completed->fresh()->search_started_at)->toBeNull();
});

test('a trip left searching past the timeout is cancelled quietly with its booking', function () {
    $push = Mockery::spy(FcmGateway::class);
    $this->app->instance(FcmGateway::class, $push);

    $ride = Trip::factory()->create(['type' => 'ride', 'status' => 'searching', 'requested_at' => now()->subWeeks(3), 'completed_at' => null]);
    $delivery = Trip::factory()->delivery()->create(['status' => 'searching', 'requested_at' => now()->subDays(3), 'completed_at' => null]);
    DeliveryDetails::factory()->for($delivery)->create();

    $this->artisan('trips:backfill-passenger-model', ['--dry-run' => true])
        ->expectsOutputToContain('Would cancel 2 trip(s) left searching past the search timeout.')
        ->assertExitCode(0);
    expect($ride->fresh()->status)->toBe('searching');

    $this->artisan('trips:backfill-passenger-model')
        ->expectsOutputToContain('Cancelled 2 trip(s) left searching past the search timeout.')
        ->assertExitCode(0);

    expect($ride->fresh()->status)->toBe('cancelled')
        ->and($ride->fresh()->search_started_at)->toBeNull()
        ->and(TripPassenger::query()->where('trip_id', $ride->id)->value('status'))->toBe('cancelled')
        ->and($delivery->fresh()->status)->toBe('cancelled')
        ->and(DeliveryDetails::query()->where('trip_id', $delivery->id)->value('status'))->toBe('cancelled');

    $push->shouldNotHaveReceived('sendToUser');
});
