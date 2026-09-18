<?php

namespace App\Services\Wallet;

use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WithdrawalRequest;
use App\Services\Auth\OtpService;
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
    private const string PIN_RESET_CHANNEL = 'sms';

    private const string PIN_RESET_PURPOSE = 'wallet_pin_reset';

    public function __construct(
        private PaymentGateway $paymentGateway,
        private WithdrawChargeService $withdrawChargeService,
        private OtpService $otpService,
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

    public function requestPinReset(User $user): void
    {
        $this->getWallet($user);

        $this->otpService->generateOTP($user->phone, $user->email, self::PIN_RESET_CHANNEL, self::PIN_RESET_PURPOSE);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function resetPin(User $user, array $data): Wallet
    {
        $wallet = $this->getWallet($user);

        $this->otpService->verify($user->phone, $data['code'], self::PIN_RESET_PURPOSE);

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

        $isMobileMoney = $data['channel'] === 'mobile_money';
        $breakdown = $this->withdrawChargeService->calculateChargeBreakdown($data['channel'], $data['account_identifier'], $amount);
        $baseCharge = $breakdown['base_charge'];
        // The Yo!/platform charge (base_charge) is recorded as its own ledger transaction
        // below rather than being deducted from the rider's wallet — only the telecom
        // charge affects the wallet. All withdrawals are mobile money for now.
        $charge = $breakdown['operator_charge'];
        $walletDebit = round($amount + $charge, 2);

        if ($walletDebit > (float) $wallet->balance) {
            throw ValidationException::withMessages([
                'amount' => 'Insufficient wallet balance to cover this withdrawal and the applicable charges.',
            ]);
        }

        return DB::transaction(function () use ($user, $wallet, $data, $amount, $charge, $baseCharge, $isMobileMoney, $walletDebit): WithdrawalRequest {
            $balanceBefore = (float) $wallet->balance;
            $reference = (string) Str::uuid();
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
                : round($balanceBefore - $walletDebit, 2);

            $withdrawalRequest = WithdrawalRequest::query()->create([
                'user_id' => $user->id,
                'wallet_id' => $wallet->id,
                'amount' => $amount,
                'charge' => $charge,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'channel' => $data['channel'],
                'provider' => $data['provider'],
                'account_identifier_masked' => Str::mask($data['account_identifier'], '*', 0, -4),
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
                'amount' => $walletDebit,
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

            // Ledger-only record of the Yo!/platform charge — credited to the system
            // account, not the rider's wallet (only the telecom charge affects that,
            // via the withdrawal transaction above).
            if ($isMobileMoney && $baseCharge > 0) {
                $systemUser = User::role('system')->firstOrFail();

                Transaction::query()->create([
                    'user_id' => $systemUser->id,
                    'wallet_id' => null,
                    'method' => 'mobile_money',
                    'direction' => 'credit',
                    'transaction_type' => 'withdrawal_charge',
                    'amount' => $baseCharge,
                    'currency_code' => $wallet->currency_code,
                    'narration' => 'Yo! Payments charge for withdrawal',
                    'reference_type' => WithdrawalRequest::class,
                    'reference_id' => $withdrawalRequest->id,
                    'status' => $transactionStatus,
                    'failure_reason' => $withdrawalFailed ? $result->failureReason : null,
                ]);
            }

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
}
