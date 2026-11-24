<?php

namespace App\Filament\Resources\PricingRules\Schemas;

use App\Models\PricingRule;
use App\Models\Zone;
use Closure;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class PricingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // On create, one rule is created per selected zone (see CreatePricingRule).
                CheckboxList::make('zone_ids')
                    ->label('Zones')
                    ->options(fn (): array => Zone::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->helperText('The pricing below is applied to every zone you select.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->searchable()
                    ->bulkToggleable()
                    ->visibleOn('create')
                    ->required()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $taken = self::zonesWithRule((array) $value, $get('vehicle_type_id'));

                            if ($taken !== []) {
                                $fail('A pricing rule already exists for this vehicle type in: '.implode(', ', $taken).'. Deselect these zones, or use "Copy Pricing" on an existing rule to replace their prices.');
                            }
                        },
                    ]),
                Select::make('zone_id')
                    ->relationship('zone', 'name')
                    ->hiddenOn('create')
                    ->required(),
                Select::make('vehicle_type_id')
                    ->relationship('vehicleType', 'name')
                    ->required()
                    ->rule(
                        fn (?PricingRule $record, Get $get): Unique => Rule::unique(PricingRule::class, 'vehicle_type_id')
                            ->where('zone_id', $get('zone_id'))
                            ->ignore($record),
                        // On create the zones field checks for existing rules; on edit only check when the zone / vehicle type pair has changed
                        fn (?PricingRule $record, Get $get): bool => $record !== null
                            && ($record->zone_id != $get('zone_id') || $record->vehicle_type_id != $get('vehicle_type_id')),
                    )
                    ->validationMessages([
                        'unique' => 'A pricing rule already exists for this vehicle type in the selected zone.',
                    ]),
                TextInput::make('base_fare')
                    ->required()
                    ->numeric(),
                TextInput::make('per_km_rate')
                    ->required()
                    ->numeric(),
                TextInput::make('per_minute_rate')
                    ->required()
                    ->numeric(),
                TextInput::make('minimum_fare')
                    ->required()
                    ->numeric(),
                TextInput::make('cancellation_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('commission_rate')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('surge_multiplier')
                    ->required()
                    ->numeric()
                    ->default(1),
                TextInput::make('currency_code')
                    ->required()
                    ->default('UGX'),
                DateTimePicker::make('effective_from'),
                DateTimePicker::make('effective_to'),
            ]);
    }

    /**
     * Names of the given zones that already have a rule (deleted ones included) for the vehicle type.
     *
     * @param  array<int, int|string>  $zoneIds
     * @return array<int, string>
     */
    private static function zonesWithRule(array $zoneIds, int|string|null $vehicleTypeId): array
    {
        if (blank($vehicleTypeId)) {
            return [];
        }

        return Zone::query()
            ->whereKey($zoneIds)
            ->whereIn('id', PricingRule::withTrashed()->where('vehicle_type_id', $vehicleTypeId)->select('zone_id'))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }
}
