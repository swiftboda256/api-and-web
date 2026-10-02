<?php

namespace App\Filament\Resources\Transactions\Actions;

use App\Jobs\ResolvePendingTransactionJob;
use App\Models\Transaction;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Wallet\TransactionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Record actions shared by the transactions table and the transaction details page.
 */
class TransactionActions
{
    /**
     * Only these types are resolved by TransactionService::resolvePendingTransaction();
     * any other type would be a silent no-op there.
     */
    private const array RESOLVABLE_TYPES = ['topup', 'trip_payment', 'withdrawal'];

    public static function checkStatus(): Action
    {
        return Action::make('checkStatus')
            ->label('Check status')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('info')
            ->visible(fn (Transaction $record): bool => $record->status === 'pending'
                && filled($record->gateway_reference)
                && in_array($record->transaction_type, self::RESOLVABLE_TYPES, true))
            ->action(function (Transaction $record): void {
                // Same logic as the scheduled poller, run inline so the admin sees the gateway's answer.
                $result = app()->call([new ResolvePendingTransactionJob($record->id), 'check']);

                $record->refresh();

                $notification = match (true) {
                    $record->status === 'completed' => Notification::make()->title('Transaction completed')->success(),
                    $record->status === 'failed' => Notification::make()->title('Transaction failed')->body($record->failure_reason)->danger(),
                    $result === null => Notification::make()->title('Unable to check status')->body('The gateway could not be reached. Try again shortly.')->danger(),
                    $result->status === MobileMoneyTransactionStatus::Indeterminate => Notification::make()
                        ->title('Status could not be confirmed')
                        ->body($result->failureReason ?? 'The gateway could not confirm an outcome. The transaction is still pending.')
                        ->warning(),
                    default => Notification::make()->title('Still pending')->body('The gateway has not confirmed an outcome yet. Try again shortly.')->warning(),
                };

                $notification->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cancel transaction')
            ->modalDescription('This marks the transaction as failed and runs the usual failure handling (e.g. a withdrawal is refunded to the wallet). It does not stop a payment the gateway is still processing — check its status first.')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->placeholder('Cancelled by admin.')
                    ->maxLength(255),
            ])
            ->visible(fn (Transaction $record): bool => $record->status === 'pending'
                && in_array($record->transaction_type, self::RESOLVABLE_TYPES, true))
            ->action(function (Transaction $record, array $data, TransactionService $transactionService): void {
                $transactionService->resolvePendingTransaction(
                    $record->id,
                    succeeded: false,
                    networkReference: null,
                    failureReason: filled($data['reason'] ?? null) ? $data['reason'] : 'Cancelled by admin.',
                );

                $record->refresh();

                Notification::make()
                    ->title($record->status === 'failed' ? 'Transaction cancelled' : 'Transaction was already resolved')
                    ->success()
                    ->send();
            });
    }
}
