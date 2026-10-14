<?php

namespace App\Filament\Resources\Transactions\Actions;

use App\Jobs\ResolvePendingTransactionJob;
use App\Models\Transaction;
use App\Services\Payment\Constants\MobileMoneyTransactionStatus;
use App\Services\Wallet\TransactionReversalService;
use App\Services\Wallet\TransactionService;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Record actions shared by the transactions table and the transaction details page.
 */
class TransactionActions
{
    /**
     * Only these types are resolved by TransactionService::resolvePendingTransaction();
     * any other type would be a silent no-op there.
     */
    private const array RESOLVABLE_TYPES = ['topup', 'trip_payment', 'withdrawal', 'reversal'];

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

    public static function reverse(): Action
    {
        return Action::make('reverse')
            ->label('Reverse')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading('Reverse transaction')
            ->modalDescription(fn (Transaction $record): string => match ($record->transaction_type) {
                'topup' => 'The amount will be debited from the user\'s wallet and sent back to the mobile-money number that paid. This cannot be undone.',
                default => $record->method === 'mobile_money'
                    ? 'The payout will be debited from the rider\'s wallet, the commission voided, and the full fare sent back to the customer\'s mobile-money number. This cannot be undone.'
                    : 'The payout will be debited from the rider\'s wallet, the commission voided, and the full fare credited back to the customer\'s wallet. This cannot be undone.',
            })
            ->modalSubmitActionLabel('Reverse')
            ->schema(fn (Transaction $record): array => array_values(array_filter([
                Textarea::make('reason')
                    ->label('Reason for reversal')
                    ->required()
                    ->maxLength(255),
                // Wallet reversals have no disbursement, so no charges to choose.
                $record->method === 'mobile_money'
                    ? Radio::make('charges')
                        ->label('Disbursement charges')
                        ->options([
                            'with' => 'With charges',
                            'minus' => 'Minus charges',
                        ])
                        ->descriptions([
                            'with' => 'The company pays the charges; the payer receives the full amount.',
                            'minus' => 'The network charge is deducted from the amount the payer receives.',
                        ])
                        ->default('with')
                        ->required()
                    : null,
            ])))
            // TransactionPolicy::update -> Update:Transaction permission.
            ->authorize('update')
            ->visible(fn (Transaction $record): bool => app(TransactionReversalService::class)->isReversible($record))
            ->action(function (Transaction $record, array $data, TransactionReversalService $transactionReversalService): void {
                try {
                    $reversal = $transactionReversalService->reverse(
                        $record,
                        reason: $data['reason'],
                        absorbCharges: ($data['charges'] ?? 'with') === 'with',
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->title('Unable to reverse transaction')
                        ->body(collect($exception->errors())->flatten()->first())
                        ->danger()
                        ->send();

                    return;
                }

                $notification = $reversal->status === 'completed'
                    ? Notification::make()->title('Transaction reversed')->success()
                    : Notification::make()
                        ->title('Reversal initiated')
                        ->body('The disbursement is awaiting confirmation from the gateway. Use "Check status" on the reversal transaction to follow up.')
                        ->warning();

                $notification->send();
            });
    }
}
