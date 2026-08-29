<?php

namespace App\Services\Payment\Contracts;

use App\Services\Payment\MobileMoneyResult;

interface PaymentGateway
{
    /**
     * A short identifier for this gateway (e.g. 'mock', 'mtn_momo', 'flutterwave'),
     * recorded on transactions so the source of a charge is traceable.
     */
    public function name(): string;

    /**
     * Pull funds from a customer's mobile money wallet into the gateway account.
     */
    public function collectFromMobileMoney(string $phone, float $amount, string $currencyCode, string $reference, string $narrative): MobileMoneyResult;

    /**
     * Push funds from the gateway account to a beneficiary's mobile money wallet.
     */
    public function disburseToMobileMoney(string $phone, float $amount, string $currencyCode, string $reference, string $narrative): MobileMoneyResult;

    /**
     * Look up the current status of a previously initiated transaction using the
     * gateway's own transaction reference (as returned on the initiating call's result).
     */
    public function checkTxnStatus(string $transactionReference): MobileMoneyResult;
}
