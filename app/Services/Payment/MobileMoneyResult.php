<?php

namespace App\Services\Payment;

use App\Services\Payment\Constants\GatewayStatus;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;

readonly class MobileMoneyResult
{
    public function __construct(
        public MobileMoneyTransactionStatus $status,
        public ?string $transactionReference,
        public ?string $gatewayReference,
        public ?float $amount = null,
        public ?string $failureReason = null,
        private ?GatewayStatus $gatewayStatus = null,
    ) {}

    /**
     * The gateway's own answer, which tells apart the reasons behind Indeterminate.
     */
    public function gatewayStatus(): GatewayStatus
    {
        return $this->gatewayStatus ?? GatewayStatus::from($this->status->value);
    }
}
