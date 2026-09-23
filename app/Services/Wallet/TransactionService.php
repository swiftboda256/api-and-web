<?php

namespace App\Services\Wallet;

use App\Models\DeliveryDetails;
use App\Models\Transaction;
use App\Models\TripPassenger;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Services\Payment\YoPaymentService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

readonly class TransactionService
{
    /**
     * Verifies and resolves a Yo! Instant Payment Notification (docs section
     * 6.3). Always a no-op on invalid signature, a missing external reference,
     * or an unmatched transaction — logged and ignored rather than throwing,
     * since the controller must always answer 200 OK so Yo! doesn't retry.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handleYoSuccessIPN(YoPaymentService $yoService, array $payload, string $signature): void
    {
        Log::info('yo.ipn.received', $payload);

        if (! $yoService->verifyIpnSignature($payload, $signature)) {
            Log::warning('yo.ipn.invalid_signature', $payload);

            return;
        }

        $externalReference = $payload['external_ref'] ?? null;

        if (! $externalReference) {
            Log::warning('yo.ipn.missing_external_ref', $payload);

            return;
        }

        $transaction = Transaction::query()
            ->where('external_reference', $externalReference)
            ->first();

        if (! $transaction) {
            Log::warning('yo.ipn.unmatched_transaction', $payload);

            return;
        }

        $this->resolvePendingTransaction($transaction->id, succeeded: true, networkReference: $payload['network_ref'] ?? null, failureReason: null);
    }

    /**
     * Verifies and resolves a Yo! Transaction Failure Notification (docs
     * section 6.4). Same always-a-no-op-on-mismatch behaviour as resolveYoIpn().
     */
    public function handleYoFailureIPN(YoPaymentService $yoService, string $reference, ?string $initDate, string $verification): void
    {
        Log::info('yo.failure_notification.received', [
            'failed_transaction_reference' => $reference,
            'transaction_init_date' => $initDate,
        ]);

        if (! $yoService->verifyFailureNotificationSignature([
            'failed_transaction_reference' => $reference,
            'transaction_init_date' => $initDate,
        ], $verification)) {
            Log::warning('yo.failure_notification.invalid_signature', ['failed_transaction_reference' => $reference]);

            return;
        }

        $transaction = Transaction::query()
            ->where(function ($query) use ($reference): void {
                $query->where('external_reference', $reference)
                    ->orWhere('gateway_reference', $reference);
            })
            ->first();

        if (! $transaction) {
            Log::warning('yo.failure_notification.unmatched_transaction', ['reference' => $reference]);

            return;
        }

        $this->resolvePendingTransaction($transaction->id, succeeded: false, networkReference: null, failureReason: 'Yo! Payments reported this transaction as failed.');
    }

    /**
     * Resolves a still-pending gateway transaction to its final state — shared
     * by the Yo! IPN webhook, the failure notification webhook, and the
     * pending-transaction poller, so the lock/no-longer-pending guard only
     * lives in one place. A no-op if the transaction is no longer pending
     * (already resolved by whichever of those three got there first).
     *
     * Dispatches to one processing method per transaction type — these are
     * the only three transaction types ever created with a gateway/external
     * reference, so the only three an IPN or failure notification can match.
     */
    public function resolvePendingTransaction(int $transactionId, bool $succeeded, ?string $networkReference, ?string $failureReason): void
    {
        DB::transaction(function () use ($transactionId, $succeeded, $networkReference, $failureReason): void {
            $transaction = Transaction::query()->whereKey($transactionId)->lockForUpdate()->first();

            if (! $transaction || $transaction->status !== 'pending') {
                return;
            }

            match ($transaction->transaction_type) {
                'topup' => $this->processTopUp($transaction, $succeeded, $networkReference, $failureReason),
                'trip_payment' => $this->processTripPayment($transaction, $succeeded, $networkReference, $failureReason),
                'withdrawal' => $this->processWithdrawal($transaction, $succeeded, $networkReference, $failureReason),
                default => Log::warning('yo.unhandled_transaction_type', ['transaction_id' => $transaction->id, 'transaction_type' => $transaction->transaction_type]),
            };
        });
    }

    /**
     * Wallet top-up: credit the wallet on success, mark the transaction
     * completed/failed either way.
     */
    private function processTopUp(Transaction $transaction, bool $succeeded, ?string $networkReference, ?string $failureReason): void
    {
        $balanceBefore = null;
        $balanceAfter = null;

        if ($succeeded && $transaction->wallet_id) {
            $wallet = Wallet::query()->whereKey($transaction->wallet_id)->lockForUpdate()->first();
            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = round($balanceBefore + (float) $transaction->amount, 2);

            $wallet->update(['balance' => $balanceAfter]);
        }

        $transaction->update([
            'status' => $succeeded ? 'completed' : 'failed',
            'network_reference' => $networkReference ?? $transaction->network_reference,
            'balance_before' => $balanceBefore ?? $transaction->balance_before,
            'balance_after' => $balanceAfter ?? $transaction->balance_after,
            'failure_reason' => $succeeded ? null : ($failureReason ?? 'Yo! Payments reported this transaction as failed.'),
        ]);
    }

    /**
     * Trip payment (mobile money): mark the customer's payment
     * completed/failed, then on success credit the rider's pending payout
     * and record the commission, or mark the payout failed otherwise.
     *
     * `reference_type` is TripPassenger::class (a ride/ride_share passenger's segment) or
     * DeliveryDetails::class (a delivery/delivery_share segment) -- both settle the same way,
     * just against a different owning record.
     */
    private function processTripPayment(Transaction $payment, bool $succeeded, ?string $networkReference, ?string $failureReason): void
    {
        $payment->update([
            'status' => $succeeded ? 'completed' : 'failed',
            'network_reference' => $networkReference ?? $payment->network_reference,
            'failure_reason' => $succeeded ? null : ($failureReason ?? 'Yo! Payments reported this transaction as failed.'),
        ]);

        if (! in_array($payment->reference_type, [TripPassenger::class, DeliveryDetails::class], true) || $payment->reference_id === null) {
            return;
        }

        $settleable = match ($payment->reference_type) {
            TripPassenger::class => TripPassenger::query()->whereKey($payment->reference_id)->lockForUpdate()->first(),
            DeliveryDetails::class => DeliveryDetails::query()->whereKey($payment->reference_id)->lockForUpdate()->first(),
        };

        // A ride passenger's fare/payment data lives on its own TripFareBreakdown row;
        // a delivery still carries it directly (not moved there yet).
        $paymentRecord = $settleable instanceof TripPassenger ? $settleable->fareBreakdown : $settleable;

        $payout = Transaction::query()
            ->where('reference_type', $payment->reference_type)
            ->where('reference_id', $payment->reference_id)
            ->where('transaction_type', 'trip_payout')
            ->where('status', 'pending')
            ->lockForUpdate()
            ->first();

        if ($succeeded && $payout && $payout->wallet_id) {
            $wallet = Wallet::query()->whereKey($payout->wallet_id)->lockForUpdate()->first();

            if ($wallet) {
                $balanceBefore = (float) $wallet->balance;
                $balanceAfter = round($balanceBefore + (float) $payout->amount, 2);

                $wallet->update(['balance' => $balanceAfter]);
                $payout->update([
                    'status' => 'completed',
                    'balance_before' => $balanceBefore,
                    'balance_after' => $balanceAfter,
                    'failure_reason' => null,
                ]);

                $commissionAmount = round((float) $payment->amount - (float) $payout->amount, 2);

                if ($settleable && $paymentRecord) {
                    $this->recordPassengerCommissionTransaction($settleable, 'mobile_money', $commissionAmount, $paymentRecord->currency_code);
                }
            }
        } elseif ($payout) {
            $payout->update([
                'status' => 'failed',
                'failure_reason' => $failureReason ?? 'The customer mobile-money payment failed.',
            ]);
        }

        $shouldUpdatePaymentStatus = $succeeded || $payout === null || $payout->status === 'failed';

        if ($paymentRecord && $shouldUpdatePaymentStatus) {
            $paymentRecord->update(['payment_status' => $succeeded ? 'paid' : 'failed']);
        }
    }

    /**
     * Withdrawal (mobile money): mark the transaction completed/failed, then
     * on success finalize the linked Yo!-charge ledger row and the
     * withdrawal request, or on failure fail the charge row and refund the
     * rider's wallet (the withdrawal amount + telecom charge was already
     * debited optimistically when the request was submitted).
     */
    private function processWithdrawal(Transaction $transaction, bool $succeeded, ?string $networkReference, ?string $failureReason): void
    {
        $transaction->update([
            'status' => $succeeded ? 'completed' : 'failed',
            'network_reference' => $networkReference ?? $transaction->network_reference,
            'failure_reason' => $succeeded ? null : ($failureReason ?? 'Yo! Payments reported this transaction as failed.'),
        ]);

        if ($transaction->reference_type !== WithdrawalRequest::class || $transaction->reference_id === null) {
            return;
        }

        $withdrawal = WithdrawalRequest::query()->whereKey($transaction->reference_id)->lockForUpdate()->first();

        if (! $withdrawal || $withdrawal->status !== 'processing') {
            return;
        }

        $chargeTransaction = Transaction::query()
            ->where('reference_type', WithdrawalRequest::class)
            ->where('reference_id', $withdrawal->id)
            ->where('transaction_type', 'withdrawal_charge')
            ->where('status', 'pending')
            ->lockForUpdate()
            ->first();

        if ($succeeded) {
            $chargeTransaction?->update(['status' => 'completed']);

            $withdrawal->update([
                'status' => 'completed',
                'processed_at' => now(),
                'rejection_reason' => null,
            ]);

            return;
        }

        $chargeTransaction?->update([
            'status' => 'failed',
            'failure_reason' => $failureReason ?? 'The mobile-money disbursement failed.',
        ]);

        $wallet = Wallet::query()->whereKey($withdrawal->wallet_id)->lockForUpdate()->first();

        if ($wallet) {
            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = round($balanceBefore + (float) $transaction->amount, 2);

            $wallet->update(['balance' => $balanceAfter]);

            Transaction::query()->create([
                'user_id' => $transaction->user_id,
                'wallet_id' => $wallet->id,
                'method' => 'wallet',
                'direction' => 'credit',
                'transaction_type' => 'refund',
                'amount' => $transaction->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency_code' => $transaction->currency_code,
                'narration' => 'Reversal of failed wallet withdrawal',
                'reference_type' => WithdrawalRequest::class,
                'reference_id' => $withdrawal->id,
                'status' => 'completed',
            ]);
        }

        $withdrawal->update([
            'status' => 'rejected',
            'processed_at' => now(),
            'rejection_reason' => $failureReason ?? 'The mobile-money disbursement failed.',
        ]);
    }

    public function recordPassengerCommissionTransaction(TripPassenger|DeliveryDetails $settleable, string $method, float $commissionAmount, string $currencyCode): void
    {
        if ($commissionAmount <= 0) {
            return;
        }

        $systemUser = User::role('system')->firstOrFail();
        $label = $settleable instanceof DeliveryDetails ? 'delivery' : 'passenger';

        Transaction::query()->create([
            'user_id' => $systemUser->id,
            'wallet_id' => null,
            'method' => $method,
            'direction' => 'credit',
            'transaction_type' => 'commission',
            'amount' => $commissionAmount,
            'currency_code' => $currencyCode,
            'narration' => "Commission for trip {$settleable->trip->trip_number} ({$label} #{$settleable->id})",
            'reference_type' => $settleable::class,
            'reference_id' => $settleable->id,
            'status' => 'completed',
        ]);
    }
}
