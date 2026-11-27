<?php

namespace App\Filament\Resources\Communications\RelationManagers;

use App\Models\Communication;
use App\Models\CommunicationDelivery;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The delivery log: one row per recipient × channel, read-only.
 */
class DeliveriesRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveries';

    protected static ?string $title = 'Delivery log';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return true;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        /** @var Communication $communication */
        $communication = $this->getOwnerRecord();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('user'))
            ->defaultSort('id')
            ->poll(fn (): ?string => $communication->fresh()?->status === Communication::STATUS_COMPLETED ? null : '10s')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Recipient')
                    ->placeholder('Deleted user')
                    ->searchable(['first_name', 'last_name', 'phone', 'email']),
                TextColumn::make('channel')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Communication::CHANNELS[$state] ?? $state),
                TextColumn::make('destination')
                    ->label('Sent to')
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        CommunicationDelivery::STATUS_SENT => 'success',
                        CommunicationDelivery::STATUS_FAILED => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('failure_reason')
                    ->label('Reason')
                    ->placeholder('—')
                    // One line with an ellipsis; the full reason shows on hover.
                    ->limit(20)
                    ->tooltip(fn (CommunicationDelivery $record): ?string => mb_strlen((string) $record->failure_reason) > 20 ? $record->failure_reason : null),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options(array_intersect_key(Communication::CHANNELS, array_flip($communication->channels))),
                SelectFilter::make('status')
                    ->options([
                        CommunicationDelivery::STATUS_SENT => 'Sent',
                        CommunicationDelivery::STATUS_FAILED => 'Failed',
                        CommunicationDelivery::STATUS_PENDING => 'Pending',
                    ]),
            ]);
    }
}
