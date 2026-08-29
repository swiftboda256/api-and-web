<?php

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Payment\YoPaymentService;
use App\Services\Wallet\WalletService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Polls Yo! Payments for the current status of one pending transaction, as a
 * stand-in until IPN signature verification is configured (YO_IPN_PUBLIC_KEY).
 * Uses checkTxnStatus() (docs section 7) against the TransactionReference
 * stored in gateway_reference when the transaction was created.
 */
class ResolvePendingYoTransactionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $transactionId,
    ) {}

    public function handle(YoPaymentService $yoService, WalletService $walletService): void
    {
        $transaction = Transaction::query()
            ->where('id', $this->transactionId)
            ->where('status', 'pending')
            ->first();

        if (! $transaction || ! $transaction->gateway_reference) {
            return;
        }

        try {
            $result = $yoService->checkTxnStatus($transaction->gateway_reference);
        } catch (Throwable $e) {
            Log::error('yo.poll_status_failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        if (! in_array($result->status, [MobileMoneyTransactionStatus::Succeeded, MobileMoneyTransactionStatus::Failed], true)) {
            return;
        }

        $walletService->resolvePendingTransaction(
            $transaction->id,
            succeeded: $result->status === MobileMoneyTransactionStatus::Succeeded,
            networkReference: $result->gatewayReference,
            failureReason: $result->failureReason,
        );
    }
}
