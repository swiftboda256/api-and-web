<?php

namespace App\Observers;

use App\Models\Transaction;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;

/**
 * Tells a user when their wallet balance actually changes. Skipped for transaction types
 * that already have their own dedicated flow (top-ups and withdrawals aren't built yet --
 * see docs/IOS_PUSH_BACKEND_GUIDE.md).
 */
class TransactionObserver
{
    private const array EXCLUDED_TYPES = ['topup', 'withdrawal', 'withdrawal_charge'];

    public function created(Transaction $transaction): void
    {
        $this->notifyIfBalanceChanged($transaction);
    }

    public function updated(Transaction $transaction): void
    {
        if ($transaction->wasChanged('status')) {
            $this->notifyIfBalanceChanged($transaction);
        }
    }

    private function notifyIfBalanceChanged(Transaction $transaction): void
    {
        if ($transaction->status !== 'completed'
            || $transaction->wallet_id === null
            || in_array($transaction->transaction_type, self::EXCLUDED_TYPES, true)
        ) {
            return;
        }

        $isCredit = $transaction->direction === 'credit';
        $amount = "{$transaction->currency_code} ".number_format((float) $transaction->amount);

        // Resolved lazily -- most Transaction writes never reach this point (no wallet_id,
        // not yet completed, or an excluded type), so FcmGateway's real Messaging dependency
        // is never touched unless a push is actually about to send.
        app(FcmGateway::class)->sendToUser($transaction->user, new PushMessage(
            type: $isCredit ? 'wallet_credited' : 'wallet_debited',
            id: $transaction->id,
            title: $isCredit ? 'Wallet credited' : 'Wallet debited',
            body: $isCredit ? "{$amount} was added to your wallet." : "{$amount} was deducted from your wallet.",
            data: [
                'transaction_id' => $transaction->id,
                'amount' => (string) $transaction->amount,
                'currency_code' => $transaction->currency_code,
                'balance_after' => $transaction->balance_after !== null ? (string) $transaction->balance_after : null,
            ],
        ));
    }
}
