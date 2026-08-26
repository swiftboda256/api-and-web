<?php

namespace App\Filament\Resources\ServiceCatalogs\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ServiceCatalogForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('code')
                    ->required(),
                FileUpload::make('image_url')
                    ->disk('public')
                    ->directory('service-catalogs'),
                Select::make('service_type')
                    ->options([
                        'ride' => 'Ride',
                        'delivery' => 'Delivery',
                    ])
                    ->required(),
                Select::make('vehicleTypes')
                    ->relationship('vehicleTypes', 'name')
                    ->multiple()
                    ->preload()
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
