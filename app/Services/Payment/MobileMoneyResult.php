<?php

namespace App\Services\Payment;

use App\Services\Payment\Constants\MobileMoneyTransactionStatus;

readonly class MobileMoneyResult
{
    public function __construct(
        public MobileMoneyTransactionStatus $status,
        public ?string $transactionReference,
        public ?string $gatewayReference,
        public ?float $amount = null,
        public ?string $failureReason = null,
    ) {}
}
