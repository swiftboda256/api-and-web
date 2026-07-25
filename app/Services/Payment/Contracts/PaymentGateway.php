<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\PaymentResult;

interface PaymentGateway
{
    /**
     * A short identifier for this gateway (e.g. 'mock', 'mtn_momo', 'flutterwave'),
     * recorded on transactions so the source of a charge is traceable.
     */
    public function name(): string;

    /**
     * Charge a customer (e.g. mobile money, card) to fund a wallet top-up.
     */
    public function charge(string $phone, float $amount, string $currencyCode, string $reference): PaymentResult;
}
