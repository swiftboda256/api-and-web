<?php

namespace App\Filament\Resources\Configurations\Tables;

use App\Filament\Tables\Columns\ConfigurationValueColumn;
use App\Models\Configuration;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ConfigurationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')
                    ->description(fn ($record) => $record->description)
                    ->searchable(),
                TextColumn::make('group')
                    ->searchable(),
                ConfigurationValueColumn::make('value'),
                TextColumn::make('value_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Configuration::VALUE_TYPES[$state] ?? $state),
                TextColumn::make('guard')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Configuration::GUARDS[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'user_app' => 'info',
                        'rider_app' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->sortable(),
                TextColumn::make('updater.name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleter.name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('guard')
                    ->options(Configuration::GUARDS),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
