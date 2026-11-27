<?php

namespace App\Filament\Resources\Communications;

use App\Filament\Resources\Communications\Pages\CreateCommunication;
use App\Filament\Resources\Communications\Pages\ListCommunications;
use App\Filament\Resources\Communications\Pages\ViewCommunication;
use App\Filament\Resources\Communications\Schemas\CommunicationForm;
use App\Filament\Resources\Communications\Schemas\CommunicationInfolist;
use App\Filament\Resources\Communications\Tables\CommunicationsTable;
use App\Models\Communication;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Broadcast push / email / SMS to picked users or whole roles, with a per-recipient
 * delivery log. Sending lives in CommunicationService; a sent communication can't be edited.
 */
class CommunicationResource extends Resource
{
    protected static ?string $model = Communication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Communications';

    protected static ?string $navigationLabel = 'Communications';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return CommunicationForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return CommunicationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunicationsTable::configure($table);
    }

    public static function getRelations(): array
    {
        // The delivery log is embedded in the view page's infolist (under the message), so
        // it isn't registered here -- that would render it a second time below the page.
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunications::route('/'),
            'create' => CreateCommunication::route('/create'),
            'view' => ViewCommunication::route('/{record}'),
        ];
    }
}
