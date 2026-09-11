<?php

namespace App\Filament\Resources\SupportCategories;

use App\Filament\Resources\SupportCategories\Pages\CreateSupportCategory;
use App\Filament\Resources\SupportCategories\Pages\EditSupportCategory;
use App\Filament\Resources\SupportCategories\Pages\ListSupportCategories;
use App\Filament\Resources\SupportCategories\Schemas\SupportCategoryForm;
use App\Filament\Resources\SupportCategories\Tables\SupportCategoriesTable;
use App\Models\SupportCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SupportCategoryResource extends Resource
{
    protected static ?string $model = SupportCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLifebuoy;

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return SupportCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SupportCategoriesTable::configure($table);
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
            'index' => ListSupportCategories::route('/'),
            'create' => CreateSupportCategory::route('/create'),
            'edit' => EditSupportCategory::route('/{record}/edit'),
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
