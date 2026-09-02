<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\Payment\YoPaymentService;
use App\Services\Wallet\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Receives Yo! Payments webhooks: Instant Payment Notifications on success
 * (API docs section 6.3, handleIPN) and Transaction Failure Notifications
 * (section 6.4, handleFailure) on their own dedicated URLs. Both URLs are
 * submitted per-request via the InstantNotificationUrl/FailureNotificationUrl
 * fields on the acdepositfunds request (see YoPaymentService::collectFromMobileMoney),
 * rather than relying on a single default URL configured on the account.
 *
 * Collections are submitted with NonBlocking=TRUE (see YoPaymentService), so the
 * synchronous response is only ever PENDING; these webhooks are what actually
 * resolve a transaction to its final state once the mobile money network
 * confirms or rejects the payment. Always answers 200 OK, per the docs, so
 * Yo! does not endlessly retry — signature failures and unmatched/duplicate
 * transactions are logged and ignored rather than rejected.
 */
class YoPaymentController extends Controller
{
    public function handleIPN(Request $request, YoPaymentService $yoService, WalletService $walletService): JsonResponse
    {
        if (! $request->input('failed_transaction_reference')) {
            $payload = $request->only([
                'date_time', 'amount', 'narrative', 'network_ref',
                'external_ref', 'msisdn', 'payer_names', 'payer_email',
            ]);

            Log::info('yo.ipn.received', $payload);

            $signature = (string) $request->input('signature');

            if (! $yoService->verifyIpnSignature($payload, $signature)) {
                Log::warning('yo.ipn.invalid_signature', $payload);

                return self::success(message: 'Ignored');
            }

            $externalReference = $payload['external_ref'] ?? null;

            if (! $externalReference) {
                Log::warning('yo.ipn.missing_external_ref', $payload);

                return self::success(message: 'Ignored');
            }

            $transaction = Transaction::query()
                ->where('external_reference', $externalReference)
                ->first();

            if (! $transaction) {
                Log::warning('yo.ipn.unmatched_transaction', $payload);

                return self::success(message: 'Ignored');
            }

            $walletService->resolvePendingTransaction($transaction->id, succeeded: true, networkReference: $payload['network_ref'] ?? null, failureReason: null);
        } else {
            $reference = $request->input('failed_transaction_reference');
            $initDate = $request->input('transaction_init_date', $request->input('transaction_date'));

            Log::info('yo.failure_notification.received', [
                'failed_transaction_reference' => $reference,
                'transaction_init_date' => $initDate,
            ]);

            $verification = (string) $request->input('verification');

            if (! $yoService->verifyFailureNotificationSignature([
                'failed_transaction_reference' => $reference,
                'transaction_init_date' => $initDate,
            ], $verification)) {
                Log::warning('yo.failure_notification.invalid_signature', ['failed_transaction_reference' => $reference]);

                return self::success(message: 'Ignored');
            }

            $transaction = Transaction::query()
                ->where(function ($query) use ($reference): void {
                    $query->where('external_reference', $reference)
                        ->orWhere('gateway_reference', $reference);
                })
                ->first();

            if (! $transaction) {
                Log::warning('yo.failure_notification.unmatched_transaction', ['reference' => $reference]);

                return self::success(message: 'Ignored');
            }

            $walletService->resolvePendingTransaction($transaction->id, succeeded: false, networkReference: null, failureReason: 'Yo! Payments reported this transaction as failed.');
        }

        return self::success(message: 'Processed');
    }
}
