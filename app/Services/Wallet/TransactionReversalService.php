<?php

namespace App\Services\Wallet;

use App\Models\Transaction;
use App\Models\Trip;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\MobileMoneyResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Reverses a completed credit back the way the money came in: mobile money is
 * disbursed to the phone that paid, wallet money is credited back to the wallet
 * that paid. Every reversal row references the original transaction
 * (reference_type = Transaction), so the pending-disbursement resolver can find
 * the whole set again.
 */
readonly class TransactionReversalService
{
    private const array REVERSIBLE_TYPES = ['topup', 'trip_payout'];

    private const array REVERSIBLE_METHODS = ['mobile_money', 'wallet'];

    public function __construct(
        private PaymentGateway $paymentGateway,
        private WithdrawChargeService $withdrawChargeService,
    ) {}

    /**
     * Cheap check for UI visibility; reverse() re-checks under a lock and also
     * rejects transactions that already have a reversal in flight.
     */
    public function isReversible(Transaction $transaction): bool
    {
        return $transaction->status === 'completed'
            && $transaction->direction === 'credit'
            && $transaction->wallet_id !== null
            && in_array($transaction->transaction_type, self::REVERSIBLE_TYPES, true)
            && in_array($transaction->method, self::REVERSIBLE_METHODS, true);
    }

    /**
     * @param  bool  $absorbCharges  true = the company pays the disbursement charges
     *                               (payer receives the full amount); false = the payer
     *                               bears the operator charge. Ignored for wallet reversals.
     */
    public function reverse(Transaction $transaction, string $reason, bool $absorbCharges): Transaction
    {
        return DB::transaction(function () use ($transaction, $reason, $absorbCharges): Transaction {
            $original = Transaction::query()->whereKey($transaction->id)->lockForUpdate()->firstOrFail();

            if (! $this->isReversible($original)) {
                $this->fail('This transaction can no longer be reversed.');
            }

            $hasActiveReversal = Transaction::query()
                ->where('reference_type', Transaction::class)
                ->where('reference_id', $original->id)
                ->where('transaction_type', 'reversal')
                ->whereIn('status', ['pending', 'completed'])
                ->exists();

            if ($hasActiveReversal) {
                $this->fail('A reversal for this transaction is already in progress.');
            }

            return match ($original->transaction_type) {
                'topup' => $this->reverseTopUp($original, $reason, $absorbCharges),
                'trip_payout' => $this->reverseTripPayout($original, $reason, $absorbCharges),
            };
        });
    }

    /**
     * Resolves a pending reversal disbursement (called from
     * TransactionService::resolvePendingTransaction, inside its DB transaction).
     * On success the original is marked reversed; on failure the wallet debit
     * taken when the reversal was initiated is refunded.
     */
    public function resolvePending(Transaction $disbursement, bool $succeeded, ?string $networkReference, ?string $failureReason): void
    {
        $failureReason ??= 'The mobile-money reversal disbursement failed.';

        $disbursement->update([
            'status' => $succeeded ? 'completed' : 'failed',
            'network_reference' => $networkReference ?? $disbursement->network_reference,
            'failure_reason' => $succeeded ? null : $failureReason,
        ]);

        if ($disbursement->reference_type !== Transaction::class || $disbursement->reference_id === null) {
            return;
        }

        $original = Transaction::query()->whereKey($disbursement->reference_id)->lockForUpdate()->first();

        if (! $original) {
            return;
        }

        $siblings = Transaction::query()
            ->where('reference_type', Transaction::class)
            ->where('reference_id', $original->id)
            ->whereIn('transaction_type', ['reversal', 'reversal_charge'])
            ->where('status', 'pending')
            ->whereKeyNot($disbursement->id)
            ->lockForUpdate()
            ->get();

        foreach ($siblings as $sibling) {
            $sibling->update([
                'status' => $succeeded ? 'completed' : 'failed',
                'failure_reason' => $succeeded ? null : $failureReason,
            ]);
        }

        if ($succeeded) {
            $this->markReversed($original);

            return;
        }

        // Refund the wallet debit taken up front (the debited row carries balance_after).
        $walletDebits = $siblings->push($disbursement)->filter(fn (Transaction $row): bool => $row->transaction_type === 'reversal'
            && $row->direction === 'debit'
            && $row->wallet_id !== null
            && $row->balance_after !== null);

        foreach ($walletDebits as $debit) {
            $wallet = Wallet::query()->whereKey($debit->wallet_id)->lockForUpdate()->first();

            if (! $wallet) {
                continue;
            }

            $balanceBefore = (float) $wallet->balance;
            $balanceAfter = round($balanceBefore + (float) $debit->amount, 2);

            $wallet->update(['balance' => $balanceAfter]);

            Transaction::query()->create([
                'user_id' => $debit->user_id,
                'wallet_id' => $wallet->id,
                'method' => 'wallet',
                'direction' => 'credit',
                'transaction_type' => 'refund',
                'amount' => $debit->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency_code' => $debit->currency_code,
                'narration' => "Refund of failed reversal of transaction #{$original->id}",
                'reference_type' => Transaction::class,
                'reference_id' => $original->id,
                'status' => 'completed',
            ]);
        }
    }

    /**
     * Top-up: take the amount back out of the wallet it was credited to and
     * disburse it to the phone that paid.
     */
    private function reverseTopUp(Transaction $topUp, string $reason, bool $absorbCharges): Transaction
    {
        if ($topUp->method !== 'mobile_money' || blank($topUp->phone)) {
            $this->fail('This top-up has no paying phone number to reverse to.');
        }

        $wallet = $this->lockWalletWithBalance($topUp->wallet_id, (float) $topUp->amount, 'user');
        $narration = "Reversal of top-up #{$topUp->id}: {$reason}";

        [$result, $reference, $status, $charge] = $this->disburse($topUp->phone, (float) $topUp->amount, $topUp->currency_code, $absorbCharges, $narration);

        [$balanceBefore, $balanceAfter] = $status === 'failed'
            ? [null, null]
            : $this->debitWallet($wallet, (float) $topUp->amount);

        $reversal = Transaction::query()->create([
            'user_id' => $topUp->user_id,
            'wallet_id' => $wallet->id,
            'method' => 'mobile_money',
            'direction' => 'debit',
            'transaction_type' => 'reversal',
            'amount' => $topUp->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'currency_code' => $topUp->currency_code,
            'gateway' => $this->paymentGateway->name(),
            'gateway_reference' => $result->transactionReference,
            'external_reference' => $reference,
            'network_reference' => $result->gatewayReference,
            'phone' => $topUp->phone,
            'narration' => $narration,
            'reference_type' => Transaction::class,
            'reference_id' => $topUp->id,
            'status' => $status,
            'failure_reason' => $result->failureReason,
        ]);

        $this->recordCharge($topUp, $charge, $status, $result->failureReason);

        if ($status === 'completed') {
            $this->markReversed($topUp);
        }

        return $reversal;
    }

    /**
     * Trip payout: take the rider's earning back out of their wallet, void the
     * commission and return the full fare to the customer the way they paid.
     */
    private function reverseTripPayout(Transaction $payout, string $reason, bool $absorbCharges): Transaction
    {
        if ($payout->reference_type !== Trip::class || $payout->reference_id === null) {
            $this->fail('This payout is not linked to a trip.');
        }

        $payment = Transaction::query()
            ->where('reference_type', Trip::class)
            ->where('reference_id', $payout->reference_id)
            ->where('transaction_type', 'trip_payment')
            ->where('status', 'completed')
            ->lockForUpdate()
            ->first();

        if (! $payment || ! in_array($payment->method, self::REVERSIBLE_METHODS, true)) {
            $this->fail('No completed mobile-money or wallet payment was found for this trip.');
        }

        $riderWallet = $this->lockWalletWithBalance($payout->wallet_id, (float) $payout->amount, 'rider');
        $trip = Trip::query()->whereKey($payout->reference_id)->first();
        $narration = "Reversal of trip {$trip?->trip_number} payout #{$payout->id}: {$reason}";

        if ($payment->method === 'wallet') {
            $customerWallet = $payment->wallet_id
                ? Wallet::query()->whereKey($payment->wallet_id)->lockForUpdate()->first()
                : null;

            if (! $customerWallet) {
                $this->fail('The customer wallet that paid for this trip could not be found.');
            }

            $this->recordRiderDebit($payout, $riderWallet, $narration, 'completed');

            $balanceBefore = (float) $customerWallet->balance;
            $balanceAfter = round($balanceBefore + (float) $payment->amount, 2);

            $customerWallet->update(['balance' => $balanceAfter]);

            $refund = Transaction::query()->create([
                'user_id' => $payment->user_id,
                'wallet_id' => $customerWallet->id,
                'method' => 'wallet',
                'direction' => 'credit',
                'transaction_type' => 'reversal',
                'amount' => $payment->amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'currency_code' => $payment->currency_code,
                'narration' => $narration,
                'reference_type' => Transaction::class,
                'reference_id' => $payout->id,
                'status' => 'completed',
            ]);

            $this->markReversed($payout);

            return $refund;
        }

        if (blank($payment->phone)) {
            $this->fail('The trip payment has no paying phone number to reverse to.');
        }

        [$result, $reference, $status, $charge] = $this->disburse($payment->phone, (float) $payment->amount, $payment->currency_code, $absorbCharges, $narration);

        if ($status !== 'failed') {
            $this->recordRiderDebit($payout, $riderWallet, $narration, $status);
        }

        $refund = Transaction::query()->create([
            'user_id' => $payment->user_id,
            'wallet_id' => null,
            'method' => 'mobile_money',
            'direction' => 'credit',
            'transaction_type' => 'reversal',
            'amount' => $payment->amount,
            'currency_code' => $payment->currency_code,
            'gateway' => $this->paymentGateway->name(),
            'gateway_reference' => $result->transactionReference,
            'external_reference' => $reference,
            'network_reference' => $result->gatewayReference,
            'phone' => $payment->phone,
            'narration' => $narration,
            'reference_type' => Transaction::class,
            'reference_id' => $payout->id,
            'status' => $status,
            'failure_reason' => $result->failureReason,
        ]);

        $this->recordCharge($payout, $charge, $status, $result->failureReason);

        if ($status === 'completed') {
            $this->markReversed($payout);
        }

        return $refund;
    }

    /**
     * With charges: send amount + operator charge so the payer receives the full
     * amount, and the company bears base + operator charge. Minus charges: send
     * the amount (the network deducts its fee from it) and the company bears only
     * the base charge.
     *
     * @return array{0: MobileMoneyResult, 1: string, 2: string, 3: float}
     */
    private function disburse(string $phone, float $amount, string $currencyCode, bool $absorbCharges, string $narration): array
    {
        $breakdown = $this->withdrawChargeService->calculateChargeBreakdown('mobile_money', $phone, $amount);

        $disburseAmount = $absorbCharges ? round($amount + $breakdown['operator_charge'], 2) : $amount;
        $charge = $absorbCharges ? $breakdown['total'] : $breakdown['base_charge'];

        $reference = (string) Str::uuid();
        $result = $this->paymentGateway->disburseToMobileMoney($phone, $disburseAmount, $currencyCode, $reference, Str::limit($narration, 100, ''));

        $status = match ($result->status) {
            MobileMoneyTransactionStatus::Succeeded => 'completed',
            MobileMoneyTransactionStatus::Failed => 'failed',
            MobileMoneyTransactionStatus::Pending, MobileMoneyTransactionStatus::Indeterminate => 'pending',
        };

        if ($status === 'failed') {
            $this->fail('The mobile-money disbursement failed: '.($result->failureReason ?? 'no reason given by the gateway.'));
        }

        return [$result, $reference, $status, $charge];
    }

    private function recordRiderDebit(Transaction $payout, Wallet $riderWallet, string $narration, string $status): void
    {
        [$balanceBefore, $balanceAfter] = $this->debitWallet($riderWallet, (float) $payout->amount);

        Transaction::query()->create([
            'user_id' => $payout->user_id,
            'wallet_id' => $riderWallet->id,
            'method' => 'wallet',
            'direction' => 'debit',
            'transaction_type' => 'reversal',
            'amount' => $payout->amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceAfter,
            'currency_code' => $payout->currency_code,
            'narration' => $narration,
            'reference_type' => Transaction::class,
            'reference_id' => $payout->id,
            'status' => $status,
        ]);
    }

    /**
     * Ledger-only record of the disbursement charges the company bears, against
     * the system account (same pattern as withdrawal_charge).
     */
    private function recordCharge(Transaction $original, float $charge, string $status, ?string $failureReason): void
    {
        if ($charge <= 0) {
            return;
        }

        $systemUser = User::role('system')->firstOrFail();

        Transaction::query()->create([
            'user_id' => $systemUser->id,
            'wallet_id' => null,
            'method' => 'mobile_money',
            'direction' => 'debit',
            'transaction_type' => 'reversal_charge',
            'amount' => $charge,
            'currency_code' => $original->currency_code,
            'narration' => "Disbursement charges for reversal of transaction #{$original->id}",
            'reference_type' => Transaction::class,
            'reference_id' => $original->id,
            'status' => $status,
            'failure_reason' => $failureReason,
        ]);
    }

    /**
     * Marks the original reversed. For a trip payout the customer's payment and
     * the platform commission are voided with it and the trip marked refunded.
     */
    private function markReversed(Transaction $original): void
    {
        $original->update(['status' => 'reversed']);

        if ($original->transaction_type !== 'trip_payout' || $original->reference_type !== Trip::class) {
            return;
        }

        // Per-row updates (not a bulk query) so model events stamp updated_by.
        Transaction::query()
            ->where('reference_type', Trip::class)
            ->where('reference_id', $original->reference_id)
            ->whereIn('transaction_type', ['trip_payment', 'commission'])
            ->where('status', 'completed')
            ->lockForUpdate()
            ->get()
            ->each(fn (Transaction $transaction) => $transaction->update(['status' => 'reversed']));

        Trip::query()->whereKey($original->reference_id)->first()?->update(['payment_status' => 'refunded']);
    }

    private function lockWalletWithBalance(int $walletId, float $amount, string $owner): Wallet
    {
        $wallet = Wallet::query()->whereKey($walletId)->lockForUpdate()->first();

        if (! $wallet) {
            $this->fail("The {$owner}'s wallet could not be found.");
        }

        if ((float) $wallet->balance < $amount) {
            $this->fail("The {$owner}'s wallet balance ({$wallet->currency_code} ".number_format((float) $wallet->balance).') is too low to reverse this transaction.');
        }

        return $wallet;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function debitWallet(Wallet $wallet, float $amount): array
    {
        $balanceBefore = (float) $wallet->balance;
        $balanceAfter = round($balanceBefore - $amount, 2);

        $wallet->update(['balance' => $balanceAfter]);

        return [$balanceBefore, $balanceAfter];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['transaction' => $message]);
    }
}
