<?php

namespace App\Filament\Resources\SurgePricingSchedules\Schemas;

use App\Models\SurgePricingSchedule;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class SurgePricingScheduleForm
{
    public const array DAYS = [
        0 => 'Sunday',
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('zone_id')
                    ->relationship('zone', 'name')
                    ->required(),
                Select::make('vehicle_type_id')
                    ->relationship('vehicleType', 'name'),
                // Values match Carbon's dayOfWeek (0 = Sunday), which the surge lookup compares against.
                // On create, leaving it empty creates one schedule per day (see CreateSurgePricingSchedule).
                Select::make('day_of_week')
                    ->options(self::DAYS)
                    ->placeholder(fn (string $operation): ?string => $operation === 'create' ? 'Every day' : null)
                    ->helperText(fn (string $operation): ?string => $operation === 'create' ? 'Leave empty to create a schedule for every day of the week.' : null)
                    ->required(fn (string $operation): bool => $operation === 'edit'),
                TimePicker::make('start_time')
                    ->required()
                    ->rules([
                        fn (Get $get, ?SurgePricingSchedule $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get, $record): void {
                            $clashingDays = self::clashingDays($get, $record);

                            if ($clashingDays !== []) {
                                $fail('This time window overlaps an existing surge pricing schedule for this zone and vehicle type on: '.implode(', ', $clashingDays).'.');
                            }
                        },
                    ]),
                TimePicker::make('end_time')
                    ->required()
                    ->helperText('An end time earlier than the start time runs overnight into the next day.')
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, mixed $value, Closure $fail) use ($get): void {
                            $start = $get('start_time');

                            if (filled($start) && filled($value) && self::secondsOfDay($value) === self::secondsOfDay($start)) {
                                $fail('The end time must be different from the start time.');
                            }
                        },
                    ]),
                TextInput::make('fixed_amount')
                    ->required()
                    ->numeric(),
                TextInput::make('per_km_rate')
                    ->label('Per km rate')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Replaces the pricing rule\'s per km rate during this window. Leave empty to keep the pricing rule\'s rate.'),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }

    /**
     * Names of the days (from this form) whose window overlaps an existing schedule
     * for the same zone and vehicle type. An empty day means every day, and a window
     * whose end is before its start runs overnight into the next day.
     *
     * @return array<int, string>
     */
    private static function clashingDays(Get $get, ?SurgePricingSchedule $record): array
    {
        if (blank($get('zone_id')) || blank($get('start_time')) || blank($get('end_time'))) {
            return [];
        }

        $days = filled($get('day_of_week')) ? [(int) $get('day_of_week')] : array_keys(self::DAYS);

        $existing = SurgePricingSchedule::query()
            ->where('zone_id', $get('zone_id'))
            ->when(
                filled($get('vehicle_type_id')),
                fn ($query) => $query->where('vehicle_type_id', $get('vehicle_type_id')),
                fn ($query) => $query->whereNull('vehicle_type_id'),
            )
            ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
            ->get(['day_of_week', 'start_time', 'end_time']);

        $existingSegments = $existing->flatMap(fn (SurgePricingSchedule $schedule): array => array_merge(...array_map(
            fn (int $day): array => self::weekSegments($day, $schedule->start_time, $schedule->end_time),
            $schedule->day_of_week === null ? array_keys(self::DAYS) : [(int) $schedule->day_of_week],
        )));

        $clashing = array_filter($days, fn (int $day): bool => collect(self::weekSegments($day, $get('start_time'), $get('end_time')))
            ->contains(fn (array $segment): bool => $existingSegments->contains(
                fn (array $other): bool => $segment[0] < $other[1] && $other[0] < $segment[1],
            )));

        return array_values(array_intersect_key(self::DAYS, array_flip($clashing)));
    }

    /**
     * A schedule's window as [start, end) second offsets within the week (Sunday 00:00 = 0).
     * Overnight windows extend into the next day, wrapping Saturday night into Sunday.
     *
     * @return array<int, array{0: int, 1: int}>
     */
    private static function weekSegments(int $day, string $startTime, string $endTime): array
    {
        $week = 7 * 86400;
        $start = $day * 86400 + self::secondsOfDay($startTime);
        $end = $day * 86400 + self::secondsOfDay($endTime);

        if ($end <= $start) {
            $end += 86400;
        }

        return $end <= $week
            ? [[$start, $end]]
            : [[$start, $week], [0, $end - $week]];
    }

    private static function secondsOfDay(string $time): int
    {
        return (int) Carbon::parse($time)->secondsSinceMidnight();
    }
}
