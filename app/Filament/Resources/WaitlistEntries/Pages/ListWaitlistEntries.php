<?php

namespace App\Filament\Resources\WaitlistEntries\Pages;

use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWaitlistEntries extends ListRecords
{
    protected static string $resource = WaitlistEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
