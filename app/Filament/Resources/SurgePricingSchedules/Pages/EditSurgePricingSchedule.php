<?php

namespace App\Filament\Resources\SurgePricingSchedules\Pages;

use App\Filament\Resources\SurgePricingSchedules\SurgePricingScheduleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSurgePricingSchedule extends EditRecord
{
    protected static string $resource = SurgePricingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
