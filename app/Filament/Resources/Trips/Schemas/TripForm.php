<?php

namespace App\Filament\Resources\Trips\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class TripForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('trip_number')
                    ->required(),
                Select::make('customer_id')
                    ->relationship('customer', 'id')
                    ->required(),
                Select::make('vehicle_id')
                    ->relationship('vehicle', 'id'),
                Select::make('vehicle_type_id')
                    ->relationship('vehicleType', 'name'),
                Select::make('zone_id')
                    ->relationship('zone', 'name'),
                TextInput::make('type')
                    ->required(),
                TextInput::make('status')
                    ->required()
                    ->default('requested'),
                TextInput::make('pickup_address'),
                TextInput::make('dropoff_address'),
                DateTimePicker::make('requested_at')
                    ->required(),
                DateTimePicker::make('accepted_at'),
                DateTimePicker::make('arrived_at'),
                DateTimePicker::make('started_at'),
                DateTimePicker::make('completed_at'),
                DateTimePicker::make('cancelled_at'),
                TextInput::make('cancelled_by')
                    ->numeric(),
                Select::make('cancellation_reason_id')
                    ->relationship('cancellationReason', 'id'),
                TextInput::make('distance_km')
                    ->numeric(),
                TextInput::make('duration_minutes')
                    ->numeric(),
                TextInput::make('estimated_fare')
                    ->required()
                    ->numeric(),
                TextInput::make('final_fare')
                    ->numeric(),
                TextInput::make('currency_code')
                    ->required(),
                Select::make('promo_code_id')
                    ->relationship('promoCode', 'id'),
                TextInput::make('discount_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('payment_method'),
                TextInput::make('payment_status')
                    ->required()
                    ->default('pending'),
                TextInput::make('created_by')
                    ->numeric(),
                TextInput::make('updated_by')
                    ->numeric(),
                TextInput::make('deleted_by')
                    ->numeric(),
                Select::make('rider_id')
                    ->relationship('rider', 'id'),
            ]);
    }
}
