<?php

namespace App\Filament\Resources\PricingRules\Pages;

use App\Filament\Resources\PricingRules\PricingRuleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreatePricingRule extends CreateRecord
{
    protected static string $resource = PricingRuleResource::class;

    private int $createdCount = 1;

    /**
     * Create one pricing rule per selected zone.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $attributes = Arr::except($data, 'zone_ids');
        $zoneIds = Arr::wrap($data['zone_ids']);

        return DB::transaction(function () use ($zoneIds, $attributes): Model {
            $records = collect($zoneIds)
                ->map(fn (int|string $zoneId): Model => parent::handleRecordCreation([...$attributes, 'zone_id' => $zoneId]));

            $this->createdCount = $records->count();

            return $records->firstOrFail();
        });
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->createdCount > 1
            ? "{$this->createdCount} pricing rules created (one per zone)"
            : parent::getCreatedNotificationTitle();
    }
}
