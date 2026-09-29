<?php

namespace App\Filament\Resources\PricingRules\Schemas;

use App\Models\PricingRule;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
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
                    ->rule(
                        fn (?PricingRule $record, Get $get): Unique => Rule::unique(PricingRule::class, 'vehicle_type_id')
                            ->where('zone_id', $get('zone_id'))
                            ->ignore($record),
                        // Only check on create, or on edit when the zone / vehicle type pair has changed
                        fn (?PricingRule $record, Get $get): bool => ! $record
                            || $record->zone_id != $get('zone_id')
                            || $record->vehicle_type_id != $get('vehicle_type_id'),
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
