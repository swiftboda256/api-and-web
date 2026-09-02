<?php

namespace App\Services\Payment;

use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Payment\Contracts\PaymentGateway;
use Illuminate\Support\Str;

/**
 * Stands in until a real gateway (e.g. MTN/Airtel Mobile Money, Flutterwave) is
 * integrated. Always succeeds, so top-ups behave as if the charge cleared instantly.
 */
class MockPaymentGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'mock';
    }

    public function collectFromMobileMoney(string $phone, float $amount, string $currencyCode, string $reference, string $narrative): MobileMoneyResult
    {
        return new MobileMoneyResult(
            status: MobileMoneyTransactionStatus::Succeeded,
            transactionReference: 'mock_'.Str::uuid(),
            gatewayReference: 'mock_mno_'.Str::uuid(),
            amount: $amount,
        );
    }

    public function disburseToMobileMoney(string $phone, float $amount, string $currencyCode, string $reference, string $narrative): MobileMoneyResult
    {
        return new MobileMoneyResult(
            status: MobileMoneyTransactionStatus::Succeeded,
            transactionReference: 'mock_'.Str::uuid(),
            gatewayReference: 'mock_mno_'.Str::uuid(),
            amount: $amount,
        );
    }

    public function checkTxnStatus(string $transactionReference): MobileMoneyResult
    {
        return new MobileMoneyResult(
            status: MobileMoneyTransactionStatus::Succeeded,
            transactionReference: $transactionReference,
            gatewayReference: 'mock_mno_'.Str::uuid(),
        );
    }
}
