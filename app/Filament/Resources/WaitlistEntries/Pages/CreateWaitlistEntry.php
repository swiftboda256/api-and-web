<?php

namespace App\Filament\Resources\WaitlistEntries\Pages;

use App\Filament\Resources\WaitlistEntries\WaitlistEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateWaitlistEntry extends CreateRecord
{
    protected static string $resource = WaitlistEntryResource::class;
}
