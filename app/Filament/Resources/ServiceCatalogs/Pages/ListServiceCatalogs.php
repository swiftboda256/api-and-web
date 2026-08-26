<?php

namespace App\Filament\Resources\ServiceCatalogs\Pages;

use App\Filament\Resources\ServiceCatalogs\ServiceCatalogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceCatalogs extends ListRecords
{
    protected static string $resource = ServiceCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
