<?php

namespace App\Filament\Resources\Communications\Tables;

use App\Filament\Resources\Communications\Schemas\CommunicationInfolist;
use App\Models\Communication;
use App\Models\CommunicationDelivery;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommunicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->with('creator')
                ->withCount([
                    'deliveries as sent_count' => fn (Builder $query) => $query->where('status', CommunicationDelivery::STATUS_SENT),
                    'deliveries as failed_count' => fn (Builder $query) => $query->where('status', CommunicationDelivery::STATUS_FAILED),
                    'deliveries as pending_count' => fn (Builder $query) => $query->where('status', CommunicationDelivery::STATUS_PENDING),
                ]))
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                TextColumn::make('title')
                    ->description(fn (Communication $record): string => str($record->body)->limit(80)->toString())
                    ->searchable(['title', 'body'])
                    ->wrap(),
                TextColumn::make('channels')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Communication::CHANNELS[$state] ?? $state),
                TextColumn::make('recipients_count')
                    ->label('Recipients')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sent_count')
                    ->label('Sent')
                    ->numeric()
                    ->color('success'),
                TextColumn::make('failed_count')
                    ->label('Failed')
                    ->numeric()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('pending_count')
                    ->label('Pending')
                    ->numeric()
                    ->toggleable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => CommunicationInfolist::statusColor($state))
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('creator.name')
                    ->label('Sent by')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Sent at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('channel')
                    ->options(Communication::CHANNELS)
                    ->query(fn (Builder $query, array $data) => filled($data['value'])
                        ? $query->whereJsonContains('channels', $data['value'])
                        : $query),
                SelectFilter::make('status')
                    ->options([
                        Communication::STATUS_QUEUED => 'Queued',
                        Communication::STATUS_SENDING => 'Sending',
                        Communication::STATUS_COMPLETED => 'Completed',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                ]),
            ]);
    }
}
