<?php

use App\Models\DeliveryDetails;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Wallet\TransactionReversalService;
use Illuminate\Validation\ValidationException;

/**
 * Reversing a trip payout: trip payments, payouts and commissions are linked to the
 * booking (a ride passenger or a delivery), so the reversal finds them through it and
 * marks the booking's own payment refunded.
 */
beforeEach(function () {
    $this->customer = User::factory()->create();
    $this->rider = User::factory()->create();
    $this->customerWallet = Wallet::query()->create(['user_id' => $this->customer->id, 'balance' => 1000, 'currency_code' => 'UGX', 'status' => 'active']);
    $this->riderWallet = Wallet::query()->create(['user_id' => $this->rider->id, 'balance' => 20000, 'currency_code' => 'UGX', 'status' => 'active']);
});

/**
 * The payment (customer wallet -> trip), payout (to the rider) and commission a settled
 * wallet booking leaves behind, all linked to the booking.
 *
 * @return array{payment: Transaction, payout: Transaction, commission: Transaction}
 */
function settledWalletBooking($test, TripPassenger|DeliveryDetails $booking): array
{
    $base = ['currency_code' => 'UGX', 'reference_type' => $booking::class, 'reference_id' => $booking->id, 'status' => 'completed'];

    return [
        'payment' => Transaction::query()->create([...$base, 'user_id' => $test->customer->id, 'wallet_id' => $test->customerWallet->id, 'method' => 'wallet', 'direction' => 'debit', 'transaction_type' => 'trip_payment', 'amount' => 9000]),
        'payout' => Transaction::query()->create([...$base, 'user_id' => $test->rider->id, 'wallet_id' => $test->riderWallet->id, 'method' => 'wallet', 'direction' => 'credit', 'transaction_type' => 'trip_payout', 'amount' => 8190]),
        'commission' => Transaction::query()->create([...$base, 'user_id' => $test->rider->id, 'wallet_id' => $test->riderWallet->id, 'method' => 'wallet', 'direction' => 'debit', 'transaction_type' => 'commission', 'amount' => 810]),
    ];
}

test('reversing a ride payout refunds the customer and marks the booking refunded', function () {
    $trip = Trip::factory()->create(['type' => 'ride', 'status' => 'completed', 'rider_id' => $this->rider->id]);
    $passenger = TripPassenger::query()->create(['trip_id' => $trip->id, 'customer_id' => $this->customer->id, 'seats_requested' => 1, 'status' => 'dropped_off', 'requested_at' => now()]);
    TripFareBreakdown::query()->create(['trip_id' => $trip->id, 'passenger_id' => $passenger->id, 'customer_id' => $this->customer->id, 'estimated_fare' => 9000, 'final_fare' => 9000, 'currency_code' => 'UGX', 'payment_method' => 'wallet', 'payment_status' => 'paid']);
    $settled = settledWalletBooking($this, $passenger);

    $refund = app(TransactionReversalService::class)->reverse($settled['payout'], 'Passenger was overcharged', absorbCharges: true);

    expect($refund->transaction_type)->toBe('reversal')
        ->and((float) $refund->amount)->toBe(9000.0)
        ->and($refund->narration)->toContain($trip->trip_number)
        ->and((float) $this->customerWallet->fresh()->balance)->toBe(10000.0)
        ->and((float) $this->riderWallet->fresh()->balance)->toBe(11810.0)
        ->and($settled['payout']->fresh()->status)->toBe('reversed')
        ->and($settled['payment']->fresh()->status)->toBe('reversed')
        ->and($settled['commission']->fresh()->status)->toBe('reversed')
        ->and($passenger->fareBreakdown->fresh()->payment_status)->toBe('refunded');
});

test('reversing a delivery payout marks the delivery refunded', function () {
    $trip = Trip::factory()->delivery()->create(['status' => 'completed', 'rider_id' => $this->rider->id]);
    $delivery = DeliveryDetails::factory()->for($trip)->create(['sender_id' => $this->customer->id, 'status' => 'dropped_off', 'payment_method' => 'wallet', 'payment_status' => 'paid']);
    $settled = settledWalletBooking($this, $delivery);

    app(TransactionReversalService::class)->reverse($settled['payout'], 'Package was damaged', absorbCharges: true);

    expect($delivery->fresh()->payment_status)->toBe('refunded')
        ->and($settled['payment']->fresh()->status)->toBe('reversed')
        ->and((float) $this->customerWallet->fresh()->balance)->toBe(10000.0);
});

test('a payout not linked to a booking cannot be reversed', function () {
    $payout = Transaction::query()->create(['user_id' => $this->rider->id, 'wallet_id' => $this->riderWallet->id, 'method' => 'wallet', 'direction' => 'credit', 'transaction_type' => 'trip_payout', 'amount' => 8190, 'currency_code' => 'UGX', 'status' => 'completed']);

    expect(fn () => app(TransactionReversalService::class)->reverse($payout, 'Test', absorbCharges: true))
        ->toThrow(ValidationException::class, 'This payout is not linked to a trip booking.');

    expect($payout->fresh()->status)->toBe('completed');
});
