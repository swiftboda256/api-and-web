<?php

namespace App\Filament\Resources\ServiceCatalogs;

use App\Filament\Resources\ServiceCatalogs\Pages\CreateServiceCatalog;
use App\Filament\Resources\ServiceCatalogs\Pages\EditServiceCatalog;
use App\Filament\Resources\ServiceCatalogs\Pages\ListServiceCatalogs;
use App\Filament\Resources\ServiceCatalogs\Schemas\ServiceCatalogForm;
use App\Filament\Resources\ServiceCatalogs\Tables\ServiceCatalogsTable;
use App\Models\ServiceCatalog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ServiceCatalogResource extends Resource
{
    protected static ?string $model = ServiceCatalog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ServiceCatalogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ServiceCatalogsTable::configure($table);
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
            'index' => ListServiceCatalogs::route('/'),
            'create' => CreateServiceCatalog::route('/create'),
            'edit' => EditServiceCatalog::route('/{record}/edit'),
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
