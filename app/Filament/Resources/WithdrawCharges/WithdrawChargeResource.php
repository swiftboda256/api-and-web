<?php

namespace App\Filament\Resources\WithdrawCharges;

use App\Filament\Resources\WithdrawCharges\Pages\CreateWithdrawCharge;
use App\Filament\Resources\WithdrawCharges\Pages\EditWithdrawCharge;
use App\Filament\Resources\WithdrawCharges\Pages\ListWithdrawCharges;
use App\Filament\Resources\WithdrawCharges\Schemas\WithdrawChargeForm;
use App\Filament\Resources\WithdrawCharges\Tables\WithdrawChargesTable;
use App\Models\WithdrawCharge;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class WithdrawChargeResource extends Resource
{
    protected static ?string $model = WithdrawCharge::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Finances';

    public static function form(Schema $schema): Schema
    {
        return WithdrawChargeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WithdrawChargesTable::configure($table);
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
            'index' => ListWithdrawCharges::route('/'),
            'create' => CreateWithdrawCharge::route('/create'),
            'edit' => EditWithdrawCharge::route('/{record}/edit'),
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
