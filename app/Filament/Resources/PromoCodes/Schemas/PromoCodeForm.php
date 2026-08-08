<?php

namespace App\Filament\Resources\PromoCodes\Schemas;

use App\Models\VehicleType;
use App\Models\Zone;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PromoCodeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->required(),
                TextInput::make('description'),
                Select::make('discount_type')
                    ->options([
                        'fixed' => 'fixed',
                        'percentage' => 'percentage',
                    ])
                    ->required(),
                TextInput::make('discount_value')
                    ->required()
                    ->numeric(),
                TextInput::make('max_discount_amount')
                    ->numeric(),
                TextInput::make('min_trip_amount')
                    ->numeric(),
                TextInput::make('usage_limit_total')
                    ->numeric(),
                TextInput::make('usage_limit_per_user')
                    ->numeric(),
                Select::make('applicable_vehicle_types')
                    ->options(VehicleType::query()->pluck('name', 'id')),
                Select::make('applicable_zone_ids')
                    ->options(Zone::query()->pluck('name', 'id'))
                    ->multiple()
                    ->searchable(),
                DateTimePicker::make('valid_from'),
                DateTimePicker::make('valid_until'),
            ]);
    }
}
