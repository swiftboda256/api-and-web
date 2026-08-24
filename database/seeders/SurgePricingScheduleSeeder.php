<?php

namespace Database\Seeders;

use App\Models\SurgePricingSchedule;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class SurgePricingScheduleSeeder extends Seeder
{
    private const array PEAK_WINDOWS = [
        ['start_time' => '07:00:00', 'end_time' => '09:00:00'],
        ['start_time' => '16:00:00', 'end_time' => '19:00:00'],
    ];

    private const array WEEKDAYS = [1, 2, 3, 4, 5];

    public function run(): void
    {
        $zones = Zone::query()->get();

        foreach ($zones as $zone) {
            foreach (self::PEAK_WINDOWS as $window) {
                foreach (self::WEEKDAYS as $dayOfWeek) {
                    SurgePricingSchedule::query()->firstOrCreate(
                        [
                            'zone_id' => $zone->id,
                            'vehicle_type_id' => null,
                            'day_of_week' => $dayOfWeek,
                            'start_time' => $window['start_time'],
                            'end_time' => $window['end_time'],
                        ],
                        [
                            'multiplier' => 1.00,
                            'fixed_amount' => 50.00,
                            'is_active' => true,
                        ],
                    );
                }
            }
        }
    }
}
