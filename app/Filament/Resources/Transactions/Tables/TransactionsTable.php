<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Filament\Resources\Transactions\Actions\TransactionActions;
use App\Filament\Resources\Transactions\Actions\TransactionExportActions;
use App\Models\Transaction;
use Carbon\CarbonImmutable;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    // name is an accessor (first + other + last), so search the underlying columns.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'user',
                        fn (Builder $query) => $query->whereAny(['first_name', 'other_name', 'last_name'], 'ilike', "%{$search}%"),
                    )),
                TextColumn::make('phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('method')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'credit' ? 'success' : 'danger')
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                TextColumn::make('transaction_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                TextColumn::make('amount')
                    ->money(fn (Transaction $record): string => $record->currency_code)
                    ->sortable(),
                TextColumn::make('gateway_reference')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('external_reference')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('network_reference')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        'reversed' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('creator.name'),
                TextColumn::make('updater.name')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleter.name')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('method')
                    ->options([
                        'wallet' => 'Wallet',
                        'cash' => 'Cash',
                        'mobile_money' => 'Mobile money',
                        'card' => 'Card',
                    ]),
                SelectFilter::make('transaction_type')
                    ->label('Type')
                    ->options([
                        'topup' => 'Top-up',
                        'trip_payment' => 'Trip payment',
                        'trip_payout' => 'Trip payout',
                        'refund' => 'Refund',
                        'withdrawal' => 'Withdrawal',
                        'withdrawal_charge' => 'Withdrawal charge',
                        'commission' => 'Commission',
                        'promo_credit' => 'Promo credit',
                        'adjustment' => 'Adjustment',
                        'reversal' => 'Reversal',
                        'reversal_charge' => 'Reversal charge',
                    ])
                    ->multiple(),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'completed' => 'Completed',
                        'failed' => 'Failed',
                        'reversed' => 'Reversed',
                    ]),
                SelectFilter::make('direction')
                    ->options([
                        'credit' => 'Credit',
                        'debit' => 'Debit',
                    ]),
                Filter::make('created_at')
                    ->label('Date')
                    ->schema([
                        DatePicker::make('from')
                            ->native(false)
                            ->maxDate(fn (Get $get) => $get('until') ?: now()),
                        DatePicker::make('until')
                            ->native(false)
                            ->minDate(fn (Get $get) => $get('from'))
                            ->maxDate(now()),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $query, string $from): Builder => $query->whereDate('created_at', '>=', $from))
                        ->when($data['until'] ?? null, fn (Builder $query, string $until): Builder => $query->whereDate('created_at', '<=', $until)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators[] = Indicator::make('From '.CarbonImmutable::parse($data['from'])->toFormattedDateString())->removeField('from');
                        }

                        if ($data['until'] ?? null) {
                            $indicators[] = Indicator::make('Until '.CarbonImmutable::parse($data['until'])->toFormattedDateString())->removeField('until');
                        }

                        return $indicators;
                    }),
            ])
            ->headerActions([
                TransactionExportActions::export(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    TransactionActions::checkStatus(),
                    TransactionActions::reverse(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    TransactionExportActions::exportBulk(),
                ]),
            ]);
    }
}
