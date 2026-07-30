<?php

namespace App\Filament\Resources\Configurations\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ConfigurationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                TextInput::make('value')
                    ->required(),
                TextInput::make('group'),
                TextInput::make('description'),
                Toggle::make('is_public')
                    ->required(),
            ]);
    }
}
