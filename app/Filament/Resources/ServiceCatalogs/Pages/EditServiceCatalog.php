<?php

namespace App\Filament\Resources\ServiceCatalogs\Pages;

use App\Filament\Resources\ServiceCatalogs\ServiceCatalogResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditServiceCatalog extends EditRecord
{
    protected static string $resource = ServiceCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
