<?php

namespace App\Filament\Resources\SurgePricingSchedules\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SurgePricingScheduleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('zone_id')
                    ->relationship('zone', 'name')
                    ->required(),
                Select::make('vehicle_type_id')
                    ->relationship('vehicleType', 'name'),
                TextInput::make('day_of_week')
                    ->numeric(),
                TimePicker::make('start_time')
                    ->required(),
                TimePicker::make('end_time')
                    ->required(),
                TextInput::make('multiplier')
                    ->required()
                    ->numeric(),
                TextInput::make('fixed_amount')
                    ->required()
                    ->numeric(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
