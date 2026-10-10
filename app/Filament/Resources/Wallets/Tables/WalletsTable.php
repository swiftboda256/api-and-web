<?php

namespace App\Filament\Resources\Wallets\Tables;

use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WalletsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'user',
                        fn (Builder $query) => $query->whereAny(['first_name', 'other_name', 'last_name'], 'ilike', "%{$search}%"),
                    )),
                TextColumn::make('balance')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('currency_code')
                    ->searchable(),
                TextColumn::make('status')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('creator.name')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::sortByUserName($query, 'created_by', $direction)),
                TextColumn::make('updater.name')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::sortByUserName($query, 'updated_by', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleter.name')
                    ->sortable(query: fn (Builder $query, string $direction): Builder => self::sortByUserName($query, 'deleted_by', $direction))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
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

    /**
     * name is an accessor, so sort by the audit user's first + last name via subqueries.
     */
    private static function sortByUserName(Builder $query, string $foreignKey, string $direction): Builder
    {
        foreach (['first_name', 'last_name'] as $column) {
            $query->orderBy(
                User::query()->select($column)->whereColumn('users.id', "wallets.{$foreignKey}"),
                $direction,
            );
        }

        return $query;
    }
}
