<?php

namespace App\Services\Wallet;

use App\Models\Transaction;
use App\Models\Trip;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Payment\Contracts\PaymentGateway;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

readonly class WalletService
{
    public function __construct(
        private PaymentGateway $paymentGateway,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): Wallet
    {
        if (Wallet::query()->where('user_id', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'wallet' => 'You already have a wallet.',
            ]);
        }

        $wallet = Wallet::query()->create([
            'user_id' => $user->id,
            'currency_code' => 'UGX',
            'pin' => $data['pin'],
        ]);

        return $wallet->refresh();
    }

    public function balance(User $user): Wallet
    {
        return $this->getWallet($user);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updatePin(User $user, array $data): Wallet
    {
        $wallet = $this->getWallet($user);

        if ($wallet->pin) {
            if (empty($data['current_pin'])) {
                throw ValidationException::withMessages([
                    'current_pin' => 'Please enter your current PIN.',
                ]);
            }

            if (! Hash::check($data['current_pin'], $wallet->pin)) {
                throw ValidationException::withMessages([
                    'current_pin' => 'The current PIN you entered is incorrect.',
                ]);
            }
        }

        $wallet->update(['pin' => $data['new_pin']]);

        return $wallet;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function topUp(User $user, array $data): Transaction
    {
        $wallet = $this->getWallet($user);

        return DB::transaction(function () use ($user, $wallet, $data): Transaction {
            $reference = (string) Str::uuid();
            $phone = $data['phone'] ?? $user->phone;
            $amount = (float) $data['amount'];

            $result = $this->paymentGateway->collectFromMobileMoney($phone, $amount, $wallet->currency_code, $reference, 'Wallet top-up');

            $balanceBefore = (float) $wallet->balance;
            $succeeded = $result->status === MobileMoneyTransactionStatus::Succeeded;

            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'method' => $data['method'],
                'direction' => 'credit',
                'transaction_type' => 'topup',
                'amount' => $amount,
                'balance_before' => $succeeded ? $balanceBefore : null,
                'balance_after' => $succeeded ? round($balanceBefore + $amount, 2) : null,
                'currency_code' => $wallet->currency_code,
                'gateway' => $this->paymentGateway->name(),
                'gateway_reference' => $result->transactionReference,
                'external_reference' => $reference,
                'network_reference' => $result->gatewayReference,
                'phone' => $phone,
                'narration' => 'Wallet top-up',
                'status' => $this->mapTransactionStatus($result->status),
                'failure_reason' => $result->failureReason,
            ]);

            if ($succeeded) {
                $wallet->increment('balance', $amount);
            }

            return $transaction;
        });
    }

    private function mapTransactionStatus(MobileMoneyTransactionStatus $status): string
    {
        return match ($status) {
            MobileMoneyTransactionStatus::Succeeded => 'completed',
            MobileMoneyTransactionStatus::Failed => 'failed',
            MobileMoneyTransactionStatus::Pending, MobileMoneyTransactionStatus::Indeterminate => 'pending',
        };
    }

    /**
     * Resolves a still-pending gateway transaction to its final state — shared
     * by the Yo! IPN webhook, the failure notification webhook, and the
     * pending-transaction poller, so the lock/credit/snapshot logic only
     * lives in one place. A no-op if the transaction is no longer pending
     * (already resolved by whichever of those three got there first).
     */
    public function resolvePendingTransaction(int $transactionId, bool $succeeded, ?string $networkReference, ?string $failureReason): void
    {
        DB::transaction(function () use ($transactionId, $succeeded, $networkReference, $failureReason): void {
            $transaction = Transaction::query()->whereKey($transactionId)->lockForUpdate()->first();

            if (! $transaction || $transaction->status !== 'pending') {
                return;
            }

            $balanceBefore = null;
            $balanceAfter = null;

            if ($succeeded && $transaction->wallet_id && $transaction->direction === 'credit') {
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

            if ($transaction->transaction_type === 'trip_payment'
                && $transaction->reference_type === Trip::class
                && $transaction->reference_id !== null) {
                $this->resolvePendingTripPayout($transaction, $succeeded, $failureReason);
            }

            if ($transaction->transaction_type === 'withdrawal'
                && $transaction->reference_type === WithdrawalRequest::class
                && $transaction->reference_id !== null) {
                $this->resolvePendingWithdrawal($transaction, $succeeded, $failureReason);
            }
        });
    }

    private function resolvePendingTripPayout(Transaction $payment, bool $succeeded, ?string $failureReason): void
    {
        $trip = Trip::query()->whereKey($payment->reference_id)->lockForUpdate()->first();
        $payout = Transaction::query()
            ->where('reference_type', Trip::class)
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
            }
        } elseif ($payout) {
            $payout->update([
                'status' => 'failed',
                'failure_reason' => $failureReason ?? 'The customer mobile-money payment failed.',
            ]);
        }

        if ($trip && ($succeeded || $payout === null || $payout->status === 'failed')) {
            $trip->update(['payment_status' => $succeeded ? 'paid' : 'failed']);
        }
    }

    private function resolvePendingWithdrawal(Transaction $transaction, bool $succeeded, ?string $failureReason): void
    {
        $withdrawal = WithdrawalRequest::query()->whereKey($transaction->reference_id)->lockForUpdate()->first();

        if (! $withdrawal || $withdrawal->status !== 'processing') {
            return;
        }

        if ($succeeded) {
            $withdrawal->update([
                'status' => 'completed',
                'processed_at' => now(),
                'rejection_reason' => null,
            ]);

            return;
        }

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

    /**
     * @param  array<string, mixed>  $data
     */
    public function withdraw(User $user, array $data): WithdrawalRequest
    {
        $wallet = $this->getWallet($user);
        $amount = (float) $data['amount'];

        if (! $wallet->pin) {
            throw ValidationException::withMessages([
                'pin' => 'Please set a wallet PIN before withdrawing.',
            ]);
        }

        if (! Hash::check($data['pin'], $wallet->pin)) {
            throw ValidationException::withMessages([
                'pin' => 'The PIN you entered is incorrect.',
            ]);
        }

        if ($amount > (float) $wallet->balance) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient wallet balance.',
            ]);
        }

        return DB::transaction(function () use ($user, $wallet, $data, $amount): WithdrawalRequest {
            $balanceBefore = (float) $wallet->balance;
            $reference = (string) Str::uuid();
            $isMobileMoney = $data['channel'] === 'mobile_money';
            $result = $isMobileMoney
                ? $this->paymentGateway->disburseToMobileMoney(
                    $data['account_identifier'],
                    $amount,
                    $wallet->currency_code,
                    $reference,
                    'Wallet withdrawal',
                )
                : null;
            $transactionStatus = $result ? $this->mapTransactionStatus($result->status) : 'pending';
            $withdrawalFailed = $transactionStatus === 'failed';
            $balanceAfter = $withdrawalFailed
                ? $balanceBefore
                : round($balanceBefore - $amount, 2);

            if (! $withdrawalFailed) {
                $wallet->update(['balance' => $balanceAfter]);
            }

            $withdrawalRequest = WithdrawalRequest::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'channel' => $data['channel'],
                'provider' => $data['provider'],
                'account_identifier_masked' => $this->maskAccountIdentifier($data['account_identifier']),
                'external_reference' => $reference,
                'status' => $withdrawalFailed ? 'rejected' : ($transactionStatus === 'completed' ? 'completed' : 'processing'),
                'processed_at' => $transactionStatus !== 'pending' ? now() : null,
                'rejection_reason' => $withdrawalFailed ? $result?->failureReason : null,
            ]);

            Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'method' => $isMobileMoney ? 'mobile_money' : 'wallet',
                'direction' => 'debit',
                'transaction_type' => 'withdrawal',
                'amount' => $amount,
                'balance_before' => $withdrawalFailed ? null : $balanceBefore,
                'balance_after' => $withdrawalFailed ? null : $balanceAfter,
                'currency_code' => $wallet->currency_code,
                'gateway' => $result ? $this->paymentGateway->name() : null,
                'gateway_reference' => $result?->transactionReference,
                'external_reference' => $reference,
                'network_reference' => $result?->gatewayReference,
                'phone' => $isMobileMoney ? $data['account_identifier'] : null,
                'narration' => $isMobileMoney ? 'Mobile-money wallet withdrawal' : 'Wallet withdrawal',
                'reference_type' => WithdrawalRequest::class,
                'reference_id' => $withdrawalRequest->id,
                'status' => $transactionStatus,
                'failure_reason' => $result?->failureReason,
            ]);

            return $withdrawalRequest;
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, WithdrawalRequest>
     */
    public function withdrawalRequests(User $user, array $filters = []): LengthAwarePaginator
    {
        return WithdrawalRequest::query()
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Transaction>
     */
    public function history(User $user, array $filters): LengthAwarePaginator
    {
        $wallet = $this->getWallet($user);

        $query = Transaction::query()
            ->where('wallet_id', $wallet->id)
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('transaction_type', $type))
            ->when($filters['direction'] ?? null, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->where('created_at', '>=', CarbonImmutable::parse($from)))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->where('created_at', '<=', CarbonImmutable::parse($to)))
            ->orderByDesc('created_at');

        return $query->paginate((int) ($filters['per_page'] ?? 15));
    }

    private function getWallet(User $user): Wallet
    {
        $wallet = Wallet::query()->where('user_id', $user->id)->first();

        if (! $wallet) {
            throw ValidationException::withMessages([
                'wallet' => 'You don\'t have a wallet yet. Please create one first.',
            ]);
        }

        return $wallet;
    }

    private function maskAccountIdentifier(string $value): string
    {
        $length = strlen($value);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4).substr($value, -4);
    }
}
