<?php

namespace App\Services\Payment;

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

    public function charge(string $phone, float $amount, string $currencyCode, string $reference): PaymentResult
    {
        return new PaymentResult(
            successful: true,
            gatewayReference: 'mock_'.Str::uuid(),
        );
    }
}
