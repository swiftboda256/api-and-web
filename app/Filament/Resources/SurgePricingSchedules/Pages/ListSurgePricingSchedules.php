<?php

namespace App\Filament\Resources\SurgePricingSchedules\Pages;

use App\Filament\Resources\SurgePricingSchedules\SurgePricingScheduleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSurgePricingSchedules extends ListRecords
{
    protected static string $resource = SurgePricingScheduleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
