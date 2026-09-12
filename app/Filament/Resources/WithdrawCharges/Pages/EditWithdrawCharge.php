<?php

namespace App\Filament\Resources\WithdrawCharges\Pages;

use App\Filament\Resources\WithdrawCharges\WithdrawChargeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditWithdrawCharge extends EditRecord
{
    protected static string $resource = WithdrawChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
