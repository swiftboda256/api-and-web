<?php

namespace App\Filament\Resources\WaitlistEntries;

use App\Filament\Resources\WaitlistEntries\Pages\CreateWaitlistEntry;
use App\Filament\Resources\WaitlistEntries\Pages\EditWaitlistEntry;
use App\Filament\Resources\WaitlistEntries\Pages\ListWaitlistEntries;
use App\Filament\Resources\WaitlistEntries\Schemas\WaitlistEntryForm;
use App\Filament\Resources\WaitlistEntries\Tables\WaitlistEntriesTable;
use App\Models\WaitlistEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WaitlistEntryResource extends Resource
{
    protected static ?string $model = WaitlistEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return WaitlistEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WaitlistEntriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWaitlistEntries::route('/'),
            'create' => CreateWaitlistEntry::route('/create'),
            'edit' => EditWaitlistEntry::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
