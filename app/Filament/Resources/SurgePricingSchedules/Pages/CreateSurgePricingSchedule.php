<?php

namespace App\Filament\Resources\SurgePricingSchedules\Pages;

use App\Filament\Resources\SurgePricingSchedules\Schemas\SurgePricingScheduleForm;
use App\Filament\Resources\SurgePricingSchedules\SurgePricingScheduleResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateSurgePricingSchedule extends CreateRecord
{
    protected static string $resource = SurgePricingScheduleResource::class;

    private int $createdCount = 1;

    /**
     * No day selected means "every day": create one schedule per day of the week.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        if (filled($data['day_of_week'] ?? null)) {
            return parent::handleRecordCreation($data);
        }

        return DB::transaction(function () use ($data): Model {
            $records = collect(array_keys(SurgePricingScheduleForm::DAYS))
                ->map(fn (int $day): Model => parent::handleRecordCreation([...$data, 'day_of_week' => $day]));

            $this->createdCount = $records->count();

            return $records->first();
        });
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return $this->createdCount > 1
            ? "{$this->createdCount} surge pricing schedules created (one per day)"
            : parent::getCreatedNotificationTitle();
    }
}
