<?php

namespace App\Filament\Resources\ServiceCatalogs\Pages;

use App\Filament\Resources\ServiceCatalogs\ServiceCatalogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateServiceCatalog extends CreateRecord
{
    protected static string $resource = ServiceCatalogResource::class;
}
