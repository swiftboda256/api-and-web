<?php

namespace App\Filament\Resources\Communications\Pages;

use App\Filament\Resources\Communications\CommunicationResource;
use App\Filament\Resources\Communications\Widgets\CommunicationDeliveryStats;
use Filament\Resources\Pages\ViewRecord;

class ViewCommunication extends ViewRecord
{
    protected static string $resource = CommunicationResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            CommunicationDeliveryStats::class,
        ];
    }
}
