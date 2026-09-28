<?php

namespace App\Filament\Resources\Zones\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->label('Code')
                    ->helperText('Used as the district code in rider references, e.g. KLA for Kampala.')
                    ->maxLength(10)
                    ->required(),
                TextInput::make('city')
                    ->required(),
                TextInput::make('country')
                    ->required(),
                TextInput::make('currency_code')
                    ->required(),
                TextInput::make('timezone')
                    ->required(),
                TextInput::make('minimum_negative_balance')
                    ->label('Minimum rider wallet balance')
                    ->helperText('Lowest a rider\'s wallet may go when cash-trip commission is deducted, e.g. -20000. Use 0 to disallow a negative balance.')
                    ->required()
                    ->numeric()
                    ->maxValue(0)
                    ->default(0),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
