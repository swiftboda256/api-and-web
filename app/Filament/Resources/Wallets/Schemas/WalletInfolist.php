<?php

namespace App\Filament\Resources\Wallets\Schemas;

use App\Models\Wallet;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class WalletInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.id')
                    ->label('User'),
                TextEntry::make('balance')
                    ->numeric(),
                TextEntry::make('currency_code'),
                TextEntry::make('pin')
                    ->placeholder('-'),
                TextEntry::make('status'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('updated_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('deleted_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Wallet $record): bool => $record->trashed()),
            ]);
    }
}
