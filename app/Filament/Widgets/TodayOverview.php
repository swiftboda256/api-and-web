<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Today so far vs yesterday up to the same time, with a 7-day sparkline. Cached for 60s.
 */
class TodayOverview extends StatsOverviewWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Today';

    protected ?string $description = 'Compared with yesterday at this time · trend over the last 7 days';

    protected ?string $pollingInterval = '60s';

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $today = app(DashboardMetricsService::class)->today();

        $completed = $today['completed']['today'];
        $cancelled = $today['cancelled']['today'];
        $finished = $completed + $cancelled;
        $completionRate = $finished > 0 ? round($completed / $finished * 100) : null;

        return [
            $this->stat('Trips completed', $today['completed'], Heroicon::OutlinedCheckBadge, true)
                ->description($this->change($today['completed'], true).($completionRate !== null ? " · {$completionRate}% completion" : '')),
            $this->stat('Trips cancelled', $today['cancelled'], Heroicon::OutlinedXCircle, false)
                ->description($this->change($today['cancelled'], false).($completionRate !== null ? ' · '.(100 - $completionRate).'% of finished' : '')),
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
            ->description($this->change($metric, $increaseIsGood))
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
     * @param  array{today: float, yesterday: float, trend: list<int|float>}  $metric
     */
    private function change(array $metric, bool $increaseIsGood): string
    {
        if ($metric['yesterday'] == 0.0) {
            return $metric['today'] == 0.0 ? 'Same as yesterday' : 'None by this time yesterday';
        }

        $change = round(($metric['today'] - $metric['yesterday']) / $metric['yesterday'] * 100);

        return match (true) {
            $change == 0.0 => 'Same as yesterday',
            $change > 0 => "+{$change}% vs yesterday",
            default => "{$change}% vs yesterday",
        };
    }
}
