<?php

namespace App\Services\Wallet;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
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

            $result = $this->paymentGateway->charge($phone, $amount, $wallet->currency_code, $reference);

            $transaction = Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'method' => $data['method'],
                'direction' => 'credit',
                'transaction_type' => 'topup',
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'gateway' => $this->paymentGateway->name(),
                'gateway_reference' => $result->gatewayReference,
                'phone' => $phone,
                'narration' => 'Wallet top-up',
                'status' => $result->successful ? 'completed' : 'failed',
                'failure_reason' => $result->failureReason,
            ]);

            if ($result->successful) {
                $wallet->increment('balance', $amount);
            }

            return $transaction;
        });
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
            $balanceAfter = round($balanceBefore - $amount, 2);

            $wallet->update(['balance' => $balanceAfter]);

            $withdrawalRequest = WithdrawalRequest::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'channel' => $data['channel'],
                'provider' => $data['provider'],
                'account_identifier_masked' => $this->maskAccountIdentifier($data['account_identifier']),
                'status' => 'processing',
            ]);

            Transaction::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'method' => 'wallet',
                'direction' => 'debit',
                'transaction_type' => 'withdrawal',
                'amount' => $amount,
                'currency_code' => $wallet->currency_code,
                'narration' => 'Wallet withdrawal',
                'reference_type' => WithdrawalRequest::class,
                'reference_id' => $withdrawalRequest->id,
                'status' => 'pending',
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
