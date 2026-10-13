<?php

namespace App\Filament\Resources\Configurations\Schemas;

use App\Models\Configuration;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ConfigurationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('key')
                    ->required(),
                Select::make('value_type')
                    ->options(Configuration::VALUE_TYPES)
                    ->default('text')
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set, ?string $state) => $set('value', $state === 'bool' ? false : null)),
                Group::make()
                    ->schema(fn (Get $get): array => [
                        match ($get('value_type')) {
                            'bool' => Toggle::make('value')
                                ->default(false),
                            'number' => TextInput::make('value')
                                ->numeric()
                                ->required()
                                ->dehydrateStateUsing(fn (string|int|float $state): int|float => $state + 0),
                            default => TextInput::make('value')
                                ->required(),
                        },
                    ]),
                Select::make('guard')
                    ->options(Configuration::GUARDS)
                    ->default('global')
                    ->required(),
                TextInput::make('group'),
                TextInput::make('description'),
                Toggle::make('is_public')
                    ->required(),
            ]);
    }
}
