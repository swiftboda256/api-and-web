<?php

namespace App\Filament\Resources\PricingRules\Pages;

use App\Filament\Resources\PricingRules\PricingRuleResource;
use App\Models\PricingRule;
use App\Models\VehicleType;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListPricingRules extends ListRecords
{
    protected static string $resource = PricingRuleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * "All" plus one tab per vehicle type, with rule counts (closures, so they are counted
     * at render time and stay right after a delete or restore).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['all' => Tab::make('All')->badge(fn (): int => PricingRule::query()->count())];

        foreach (VehicleType::query()->orderBy('id')->get(['id', 'name', 'code']) as $vehicleType) {
            $tabs[$vehicleType->code] = Tab::make($vehicleType->name)
                ->badge(fn (): int => PricingRule::query()->where('vehicle_type_id', $vehicleType->id)->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('vehicle_type_id', $vehicleType->id));
        }

        return $tabs;
    }

    /**
     * Clear the selection so a bulk action can't touch rules hidden by the new tab.
     */
    public function updatedActiveTab(): void
    {
        parent::updatedActiveTab();

        $this->deselectAllTableRecords();
    }
}
