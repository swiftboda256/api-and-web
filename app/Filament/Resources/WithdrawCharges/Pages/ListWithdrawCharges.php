<?php

namespace App\Filament\Resources\WithdrawCharges\Pages;

use App\Filament\Resources\WithdrawCharges\WithdrawChargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWithdrawCharges extends ListRecords
{
    protected static string $resource = WithdrawChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
