<?php

namespace App\Services\Payment;

readonly class PaymentResult
{
    public function __construct(
        public bool $successful,
        public ?string $gatewayReference,
        public ?string $failureReason = null,
    ) {}
}
