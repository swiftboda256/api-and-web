<?php

namespace App\Filament\Resources\Communications\Schemas;

use App\Filament\Resources\Communications\Pages\ViewCommunication;
use App\Filament\Resources\Communications\RelationManagers\DeliveriesRelationManager;
use App\Models\Communication;
use App\Models\User;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Livewire;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Number;

class CommunicationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([
                // Left two-thirds: the message with its delivery log right under it (the log
                // is the resource's relation manager, embedded here rather than below the page).
                Group::make([
                    Section::make('Message')
                        ->schema([
                            TextEntry::make('title'),
                            TextEntry::make('body')
                                ->label('Message')
                                ->prose()
                                ->formatStateUsing(fn (string $state): string => nl2br(e($state)))
                                ->html(),
                            TextEntry::make('channels')
                                ->badge()
                                ->formatStateUsing(fn (string $state): string => Communication::CHANNELS[$state] ?? $state),
                        ]),
                    Livewire::make(DeliveriesRelationManager::class, fn (Communication $record): array => [
                        'ownerRecord' => $record,
                        'pageClass' => ViewCommunication::class,
                    ])->key('communication-deliveries'),
                ])->columnSpan(2),
                Section::make('Details')
                    ->columnSpan(1)
                    ->schema([
                        TextEntry::make('status')
                            ->badge()
                            ->color(fn (string $state): string => self::statusColor($state))
                            ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                        TextEntry::make('target_roles')
                            ->label('Roles')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => str($state)->headline()->toString())
                            ->placeholder('None'),
                        TextEntry::make('target_user_ids')
                            ->label('Specific users')
                            ->state(fn (Communication $record): array => User::query()
                                ->whereIn('id', $record->target_user_ids ?? [])
                                ->get()
                                ->map(fn (User $user): string => $user->name ?: ($user->phone ?: '#'.$user->id))
                                ->all())
                            ->listWithLineBreaks()
                            ->limitList(10)
                            ->expandableLimitedList()
                            ->placeholder('None'),
                        TextEntry::make('recipients_count')
                            ->label('Recipients')
                            ->numeric(),
                        TextEntry::make('sms_estimated_cost')
                            ->label('SMS cost (estimate at send)')
                            ->formatStateUsing(fn (Communication $record): string => 'UGX '.Number::format($record->sms_estimated_cost)
                                ." ({$record->sms_segments} × UGX {$record->sms_unit_cost} per recipient)")
                            ->visible(fn (Communication $record): bool => in_array(Communication::CHANNEL_SMS, $record->channels, true)),
                        TextEntry::make('sms_cost_acknowledged_amount')
                            ->label('SMS cost acknowledged')
                            ->formatStateUsing(fn (Communication $record): string => 'UGX '.Number::format((int) $record->sms_cost_acknowledged_amount)
                                .' by '.($record->smsCostAcknowledger?->name ?: 'a deleted user')
                                .' at '.$record->sms_cost_acknowledged_at?->format('M j, Y H:i'))
                            ->placeholder('Not recorded')
                            ->visible(fn (Communication $record): bool => in_array(Communication::CHANNEL_SMS, $record->channels, true)),
                        TextEntry::make('creator.name')
                            ->label('Sent by')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Sent at')
                            ->dateTime(),
                        TextEntry::make('completed_at')
                            ->label('Finished at')
                            ->dateTime()
                            ->placeholder('Still sending'),
                    ]),
            ]);
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            Communication::STATUS_COMPLETED => 'success',
            Communication::STATUS_SENDING => 'warning',
            default => 'gray',
        };
    }
}
