<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Customers\CustomerResource;
use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Totals since launch: customers (how many have completed a trip, how many joined this month)
 * and commission earned (with this month so far). Cached for 60s.
 */
class AllTimeOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 4;

    protected ?string $heading = 'All time';

    protected ?string $description = 'Totals since Swift Boda started';

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 2;
    }

    protected function getStats(): array
    {
        $totals = app(DashboardMetricsService::class)->allTime();

        $customers = Stat::make('Total customers', number_format($totals['customers']))
            ->icon(Heroicon::OutlinedUsers)
            ->description(number_format($totals['customers_with_trip']).' have completed at least one trip, '
                .($totals['customers_this_month'] > 0
                    ? number_format($totals['customers_this_month']).' joined this month'
                    : 'no one has joined yet this month'))
            ->descriptionIcon(Heroicon::UserPlus)
            ->color('primary');

        if (CustomerResource::canViewAny()) {
            $customers->url(CustomerResource::getUrl('index'));
        }

        return [
            $customers,
            Stat::make('Total commission earned', 'UGX '.number_format($totals['commission']))
                ->icon(Heroicon::OutlinedReceiptPercent)
                ->description($totals['commission_this_month'] > 0
                    ? 'UGX '.number_format($totals['commission_this_month']).' of it this month'
                    : 'None earned yet this month')
                ->descriptionIcon(Heroicon::Banknotes)
                ->color('success'),
        ];
    }
}
