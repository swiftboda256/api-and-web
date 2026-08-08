<?php

namespace App\Filament\Resources\PricingRules\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PricingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('zone_id')
                    ->relationship('zone', 'name')
                    ->required(),
                Select::make('vehicle_type_id')
                    ->relationship('vehicleType', 'name')
                    ->required(),
                TextInput::make('base_fare')
                    ->required()
                    ->numeric(),
                TextInput::make('per_km_rate')
                    ->required()
                    ->numeric(),
                TextInput::make('per_minute_rate')
                    ->required()
                    ->numeric(),
                TextInput::make('minimum_fare')
                    ->required()
                    ->numeric(),
                TextInput::make('cancellation_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('commission_rate')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('surge_multiplier')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('currency_code')
                    ->required(),
                DateTimePicker::make('effective_from'),
                DateTimePicker::make('effective_to'),
                Toggle::make('is_active')
                    ->required(),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
                TextInput::make('deleted_by')
                    ->numeric(),
            ]);
    }
}
