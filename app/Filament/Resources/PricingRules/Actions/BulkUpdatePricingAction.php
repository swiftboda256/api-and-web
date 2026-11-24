<?php

namespace App\Filament\Resources\PricingRules\Actions;

use App\Models\PricingRule;
use App\Services\Pricing\PricingRuleService;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Changes only the filled-in prices on the selected pricing rules.
 */
class BulkUpdatePricingAction
{
    public static function make(): BulkAction
    {
        return BulkAction::make('bulkUpdatePricing')
            ->label('Update pricing')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn (): bool => Gate::allows('update', new PricingRule))
            ->modalHeading('Update selected pricing rules')
            ->modalDescription('Fill in only what should change. Empty fields keep each rule\'s current value. Deleted rules are skipped.')
            ->modalSubmitActionLabel('Update pricing')
            ->schema([
                Grid::make(2)->schema([
                    self::field('base_fare', 99999999.99),
                    self::field('per_km_rate', 99999999.99),
                    self::field('per_minute_rate', 99999999.99),
                    self::field('minimum_fare', 99999999.99),
                    self::field('cancellation_fee', 99999999.99),
                    self::field('commission_rate', 100)->label('Commission rate (%)'),
                    self::field('surge_multiplier', 99.99),
                ]),
            ])
            ->action(function (BulkAction $action, Collection $records, array $data, PricingRuleService $pricingRules): void {
                // Not array_filter's default: 0 is a valid price.
                $changes = array_filter($data, filled(...));

                if ($changes === []) {
                    Notification::make()->title('Fill in at least one value to change')->warning()->send();

                    $action->halt();
                }

                $updated = $pricingRules->updatePricing($records->modelKeys(), $changes);

                Notification::make()
                    ->title('Pricing updated')
                    ->body($updated === 1 ? '1 pricing rule updated.' : "{$updated} pricing rules updated.")
                    ->success()
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function field(string $name, float $max): TextInput
    {
        return TextInput::make($name)
            ->numeric()
            ->minValue(0)
            ->maxValue($max)
            ->placeholder('Keep current');
    }
}
