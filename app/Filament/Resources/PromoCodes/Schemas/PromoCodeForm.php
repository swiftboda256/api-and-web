<?php

namespace App\Filament\Resources\PromoCodes\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                TextInput::make('discount_type')
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
                TextInput::make('applicable_vehicle_types'),
                TextInput::make('applicable_zone_ids'),
                DateTimePicker::make('valid_from'),
                DateTimePicker::make('valid_until'),
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
