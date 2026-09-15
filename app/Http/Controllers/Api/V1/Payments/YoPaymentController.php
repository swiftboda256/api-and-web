<?php

namespace App\Http\Controllers\Api\V1\Payments;

use App\Http\Controllers\Controller;
use App\Services\Payment\YoPaymentService;
use App\Services\Wallet\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
 * transactions are logged and ignored rather than rejected (see TransactionService).
 */
class YoPaymentController extends Controller
{
    public function submitIPN(Request $request, YoPaymentService $yoService, TransactionService $transactionService): JsonResponse
    {
        if (! $request->input('failed_transaction_reference')) {
            $payload = $request->only([
                'date_time', 'amount', 'narrative', 'network_ref',
                'external_ref', 'msisdn', 'payer_names', 'payer_email',
            ]);

            $transactionService->handleYoSuccessIPN($yoService, $payload, (string) $request->input('signature'));
        } else {
            $initDate = $request->input('transaction_init_date', $request->input('transaction_date'));

            $transactionService->handleYoFailureIPN(
                $yoService,
                (string) $request->input('failed_transaction_reference'),
                $initDate !== null ? (string) $initDate : null,
                (string) $request->input('verification'),
            );
        }

        return self::success();
    }
}
