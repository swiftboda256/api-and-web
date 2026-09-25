<?php

namespace App\Filament\Resources\PricingRules\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

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
                    ->required()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get) => $rule->where('zone_id', $get('zone_id')),
                    )
                    ->validationMessages([
                        'unique' => 'A pricing rule already exists for this vehicle type in the selected zone.',
                    ]),
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
            ]);
    }
}
