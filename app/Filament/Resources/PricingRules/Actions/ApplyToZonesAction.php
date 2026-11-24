<?php

namespace App\Filament\Resources\PricingRules\Actions;

use App\Models\PricingRule;
use App\Models\Zone;
use App\Services\Pricing\PricingRuleService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;

/**
 * Copies a pricing rule to other zones; shared by the pricing rules table and the edit page.
 */
class ApplyToZonesAction
{
    public static function make(): Action
    {
        return Action::make('applyToZones')
            ->label('Copy Pricing')
            ->icon(Heroicon::OutlinedMapPin)
            ->color('info')
            ->visible(fn (PricingRule $record): bool => ! $record->trashed()
                && Gate::allows('create', PricingRule::class)
                && Gate::allows('update', $record))
            ->modalHeading('Copy this pricing to other zones')
            ->modalDescription(fn (PricingRule $record): string => "The saved {$record->vehicleType?->name} pricing for {$record->zone?->name} will be copied to the zones you select, including its effective dates. Zones that already have a rule for this vehicle type will have their prices replaced.")
            ->modalSubmitActionLabel('Copy Pricing')
            ->schema([
                CheckboxList::make('zone_ids')
                    ->label('Zones')
                    ->options(fn (PricingRule $record): array => Zone::query()
                        ->whereKeyNot($record->zone_id)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->descriptions(fn (PricingRule $record): array => PricingRule::query()
                        ->where('vehicle_type_id', $record->vehicle_type_id)
                        ->get(['zone_id', 'base_fare', 'per_km_rate'])
                        ->mapWithKeys(fn (PricingRule $rule): array => [
                            $rule->zone_id => 'Now: base '.number_format((float) $rule->base_fare).', per km '.number_format((float) $rule->per_km_rate),
                        ])
                        ->all())
                    ->columns(2)
                    ->searchable()
                    ->bulkToggleable()
                    ->required(),
            ])
            ->action(function (PricingRule $record, array $data, PricingRuleService $pricingRules): void {
                $result = $pricingRules->applyToZones($record, $data['zone_ids']);

                Notification::make()
                    ->title('Pricing copied')
                    ->body("{$result['updated']} updated, {$result['created']} created.")
                    ->success()
                    ->send();
            });
    }
}
