<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Payment\Contracts\PaymentGateway;
use App\Services\Payment\MobileMoneyResult;
use App\Services\Wallet\TransactionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls the gateway for a single still-pending transaction's outcome (via
 * checkTxnStatus) and resolves it, covering the case where the initiating
 * request came back NonBlocking/PENDING and no IPN ever arrived.
 */
class ResolvePendingTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $transactionId,
    ) {}

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
}
