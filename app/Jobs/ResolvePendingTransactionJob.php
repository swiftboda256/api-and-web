<?php

namespace App\Jobs;

use App\Models\Configuration;
use App\Models\Transaction;
use App\Services\Payment\Constants\GatewayStatus;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\MobileMoneyResult;
use App\Services\Wallet\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls the gateway for a single still-pending transaction's outcome (via
 * checkTxnStatus) and resolves it, covering the case where the initiating
 * request came back NonBlocking/PENDING and no IPN ever arrived.
 */
class ResolvePendingTransactionJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Seconds before the unique lock expires, so a lost job can't block
     * polling of its transaction indefinitely.
     */
    public int $uniqueFor = 60;

    public function __construct(
        public int $transactionId,
    ) {}

    /**
     * Only one queued or running check per transaction at a time.
     */
    public function uniqueId(): string
    {
        return (string) $this->transactionId;
    }

    public function handle(PaymentGateway $paymentGateway, TransactionService $transactionService): void
    {
        $this->check($paymentGateway, $transactionService);
    }

    /**
     * Checks the gateway and resolves the transaction, returning the gateway's
     * result so callers (e.g. the admin "Check status" action) can show it.
     * Null when the check was skipped or the gateway call threw.
     */
    public function check(PaymentGateway $paymentGateway, TransactionService $transactionService): ?MobileMoneyResult
    {
        Log::info('check_txn_status.started', ['transaction_id' => $this->transactionId]);

        $transaction = Transaction::query()->find($this->transactionId);

        if (! $transaction || $transaction->status !== 'pending' || ! $transaction->gateway_reference) {
            Log::info('check_txn_status.skipped', [
                'transaction_id' => $this->transactionId,
                'reason' => match (true) {
                    ! $transaction => 'not_found',
                    $transaction->status !== 'pending' => 'no_longer_pending',
                    default => 'missing_gateway_reference',
                },
            ]);

            return null;
        }

        try {
            $result = $paymentGateway->checkTxnStatus($transaction->gateway_reference);
        } catch (Throwable $e) {
            Log::error('transaction.resolve_pending.check_status_failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            $this->recordCheck($transaction, GatewayStatus::Unreachable);

            return null;
        }

        // Log check status data
        Log::info('check_txn_status.result', [
            'transaction_id' => $transaction->id,
            'status' => $result->status->value,
            'transaction_reference' => $result->transactionReference,
            'gateway_reference' => $result->gatewayReference,
            'amount' => $result->amount,
            'failure_reason' => $result->failureReason,
        ]);

        // Recorded before resolving, so a resolution that throws is backed off too.
        $this->recordCheck($transaction, $result->gatewayStatus());

        match ($result->status) {
            MobileMoneyTransactionStatus::Succeeded => $transactionService->resolvePendingTransaction(
                $transaction->id,
                succeeded: true,
                networkReference: $result->gatewayReference,
                failureReason: null,
            ),
            MobileMoneyTransactionStatus::Failed => $transactionService->resolvePendingTransaction(
                $transaction->id,
                succeeded: false,
                networkReference: null,
                failureReason: $result->failureReason ?? 'The gateway reported this transaction as failed.',
            ),
            MobileMoneyTransactionStatus::Pending, MobileMoneyTransactionStatus::Indeterminate => null,
        };

        Log::info('check_txn_status.finished', [
            'transaction_id' => $transaction->id,
            'status' => $result->status->value,
        ]);

        return $result;
    }

    /**
     * Stores the gateway's answer and when to ask again. Skips model events and
     * updated_at (it isn't a business change), and a transaction resolved meanwhile.
     */
    private function recordCheck(Transaction $transaction, GatewayStatus $gatewayStatus): void
    {
        Transaction::query()
            ->whereKey($transaction->id)
            ->where('status', 'pending')
            ->toBase()
            ->update([
                'next_status_check_at' => now()->addSeconds($this->secondsUntilNextCheck($transaction)),
                'status_check_attempts' => DB::raw('status_check_attempts + 1'),
                'last_gateway_status' => $gatewayStatus->value,
            ]);
    }

    /**
     * The wait grows with the transaction's age, in stages set through the
     * transaction_check_* configurations. Zero (first stage only) means due on
     * the scheduler's next run, every two seconds.
     */
    private function secondsUntilNextCheck(Transaction $transaction): int
    {
        $age = $transaction->created_at ? now()->getTimestamp() - $transaction->created_at->getTimestamp() : 0;

        return match (true) {
            $age < $this->setting('transaction_check_stage_1_max_age_minutes', 2, min: 0) * 60 => $this->setting('transaction_check_stage_1_interval_seconds', 0, min: 0),
            $age < $this->setting('transaction_check_stage_2_max_age_minutes', 60, min: 0) * 60 => $this->setting('transaction_check_stage_2_interval_seconds', 60, min: 1),
            $age < $this->setting('transaction_check_stage_3_max_age_minutes', 1440, min: 0) * 60 => $this->setting('transaction_check_stage_3_interval_seconds', 900, min: 1),
            default => $this->setting('transaction_check_final_interval_seconds', 21600, min: 1),
        };
    }

    /**
     * Falls back to the default for a missing or out-of-range value, so a bad
     * edit can't switch the backoff off.
     */
    private function setting(string $key, int $default, int $min): int
    {
        $value = Configuration::get($key, $default);

        return is_numeric($value) && (int) $value >= $min ? (int) $value : $default;
    }
}
