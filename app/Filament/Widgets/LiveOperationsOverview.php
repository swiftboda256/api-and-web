<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Trips\TripResource;
use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * What's happening right now. Not cached; refreshes every 30s.
 */
class LiveOperationsOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Live now';

    protected ?string $description = 'Refreshes every 30 seconds';

    protected ?string $pollingInterval = '30s';

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        $live = app(DashboardMetricsService::class)->live();
        $canViewTrips = TripResource::canViewAny();

        $searching = Stat::make('Looking for a rider', number_format($live['searching']))
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->description($live['searching_overdue'] > 0
                ? "{$live['searching_overdue']} waiting over 5 minutes"
                : 'None waiting long')
            ->descriptionIcon($live['searching_overdue'] > 0 ? Heroicon::ExclamationTriangle : Heroicon::CheckCircle)
            ->color($live['searching_overdue'] > 0 ? 'warning' : 'success');

        $active = Stat::make('Trips in progress', number_format($live['active_trips']))
            ->icon(Heroicon::OutlinedMap)
            ->description('Accepted, arrived or on the way')
            ->color('info');

        if ($canViewTrips) {
            $searching->url(TripResource::getUrl('index', ['filters' => ['status' => ['values' => DashboardMetricsService::UNASSIGNED_TRIP_STATUSES]]]));
            $active->url(TripResource::getUrl('index', ['filters' => ['status' => ['values' => DashboardMetricsService::ACTIVE_TRIP_STATUSES]]]));
        }

        return [
            Stat::make('Riders online', number_format($live['online'] + $live['on_trip']))
                ->icon(Heroicon::OutlinedSignal)
                ->description("{$live['on_trip']} on a trip · {$live['online']} available")
                ->descriptionIcon(Heroicon::User)
                ->color('success'),
            $active,
            $searching,
            Stat::make('Departing today', number_format($live['departing_today']))
                ->icon(Heroicon::OutlinedCalendarDays)
                ->description('Scheduled & posted rides')
                ->color('gray'),
        ];
    }
}
