<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('User Information')
                    ->schema([
                        TextInput::make('first_name'),
                        TextInput::make('last_name'),
                        TextInput::make('other_name'),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true),
                        TextInput::make('phone')
                            ->tel()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                        FileUpload::make('avatar_url')
                            ->disk('public')
                            ->directory('avatars')
                            ->columnSpanFull(),
                    ])->columns(2),
                Section::make('Account Information')
                    ->schema([
                        Select::make('roles')
                            ->relationship(
                                'roles',
                                'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->whereNotIn('name', ['rider', 'customer', 'system']),
                            )
                            ->multiple()
                            ->preload()
                            ->searchable(),
                        Toggle::make('notify')
                            ->live()
                            ->required(),
                        Select::make('channel')
                            ->label('Send login link via')
                            ->options(fn (Get $get): array => array_filter([
                                'email' => filled($get('email')) ? 'Email' : null,
                                'sms' => filled($get('phone')) ? 'SMS' : null,
                            ]))
                            ->visible(fn (Get $get): bool => (bool) $get('notify'))
                            ->required(fn (Get $get): bool => (bool) $get('notify')),
                    ]),
            ]);
    }
}
