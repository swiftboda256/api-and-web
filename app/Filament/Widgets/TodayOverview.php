<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Today so far vs yesterday up to the same time, with a 7-day sparkline. Cached for 60s.
 * Descriptions are plain differences ("2 fewer than yesterday by this time"), not
 * percentages, which mislead on small numbers (1 → 0 trips read as "-100%").
 */
class TodayOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Today';

    protected ?string $description = 'How today is going compared with yesterday up to the same time. The small chart shows the last 7 days.';

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $today = app(DashboardMetricsService::class)->today();

        return [
            $this->stat('Trips completed', $today['completed'], Heroicon::OutlinedCheckBadge, true),
            $this->stat('Trips cancelled', $today['cancelled'], Heroicon::OutlinedXCircle, false),
            $this->stat('Gross fares', $today['gross'], Heroicon::OutlinedBanknotes, true, money: true),
            $this->stat('Commission earned', $today['commission'], Heroicon::OutlinedReceiptPercent, true, money: true),
            $this->stat('New customers', $today['customers'], Heroicon::OutlinedUserPlus, true),
            $this->stat('New riders', $today['riders'], Heroicon::OutlinedTruck, true),
        ];
    }

    /**
     * @param  array{today: float, yesterday: float, trend: list<int|float>}  $metric
     */
    private function stat(string $label, array $metric, Heroicon $icon, bool $increaseIsGood, bool $money = false): Stat
    {
        $value = $money ? 'UGX '.number_format($metric['today']) : number_format($metric['today']);
        $direction = $metric['today'] <=> $metric['yesterday'];

        return Stat::make($label, $value)
            ->icon($icon)
            ->description($this->change($metric, $money))
            ->descriptionIcon(match ($direction) {
                1 => Heroicon::ArrowTrendingUp,
                -1 => Heroicon::ArrowTrendingDown,
                default => Heroicon::Minus,
            })
            ->color(match (true) {
                $direction === 0 => 'gray',
                ($direction === 1) === $increaseIsGood => 'success',
                default => 'danger',
            })
            ->chart($metric['trend']);
    }

    /**
     * Plain-language comparison with yesterday up to the same time, e.g. "3 more than
     * yesterday by this time" or "UGX 12,000 less than yesterday by this time".
     *
     * @param  array{today: float, yesterday: float, trend: list<int|float>}  $metric
     */
    private function change(array $metric, bool $money): string
    {
        $today = $metric['today'];
        $yesterday = $metric['yesterday'];
        $amount = fn (float $value): string => $money ? 'UGX '.number_format($value) : number_format($value);

        return match (true) {
            $today == $yesterday && $today == 0.0 => 'None yet, same as yesterday by this time',
            $today == $yesterday => 'Same as yesterday by this time',
            $today == 0.0 => 'None yet. Yesterday had '.$amount($yesterday).' by this time',
            $yesterday == 0.0 => 'Yesterday had none by this time',
            default => $amount(abs($today - $yesterday)).' '
                .($today > $yesterday ? 'more' : ($money ? 'less' : 'fewer'))
                .' than yesterday by this time',
        };
    }
}
