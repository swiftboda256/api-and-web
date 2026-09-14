<?php

namespace App\Filament\Resources\WithdrawCharges\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class WithdrawChargeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('channel')
                    ->options([
                        'mobile_money' => 'Mobile Money',
                        'bank_account' => 'Bank Account',
                        'wallet' => 'Wallet',
                    ])
                    ->helperText('Leave empty to apply to all withdrawal channels.'),
                TextInput::make('min_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('max_amount')
                    ->numeric()
                    ->helperText('Leave empty for no upper limit.'),
                TextInput::make('base_charge')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Flat UGX fee applied to every withdrawal in this tier.'),
                TextInput::make('mtn_charge')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Added on top of the base charge for MTN Mobile Money numbers.'),
                TextInput::make('airtel_charge')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Added on top of the base charge for Airtel Money numbers.'),
            ]);
    }
}
