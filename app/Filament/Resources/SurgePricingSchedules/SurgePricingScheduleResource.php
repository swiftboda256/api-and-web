<?php

namespace App\Filament\Resources\SurgePricingSchedules;

use App\Filament\Resources\SurgePricingSchedules\Pages\CreateSurgePricingSchedule;
use App\Filament\Resources\SurgePricingSchedules\Pages\EditSurgePricingSchedule;
use App\Filament\Resources\SurgePricingSchedules\Pages\ListSurgePricingSchedules;
use App\Filament\Resources\SurgePricingSchedules\Schemas\SurgePricingScheduleForm;
use App\Filament\Resources\SurgePricingSchedules\Tables\SurgePricingSchedulesTable;
use App\Models\SurgePricingSchedule;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SurgePricingScheduleResource extends Resource
{
    protected static ?string $model = SurgePricingSchedule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|UnitEnum|null $navigationGroup = 'Pricing';

    public static function form(Schema $schema): Schema
    {
        return SurgePricingScheduleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SurgePricingSchedulesTable::configure($table);
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
            'index' => ListSurgePricingSchedules::route('/'),
            'create' => CreateSurgePricingSchedule::route('/create'),
            'edit' => EditSurgePricingSchedule::route('/{record}/edit'),
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
