<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class PaymentMethodsChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 10;

    protected ?string $heading = 'Payment methods';

    protected ?string $description = 'Completed bookings in the last 30 days';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '240px';

    private const COLORS = [
        'cash' => '#10b981',
        'wallet' => '#f59e0b',
        'mobile_money' => '#0ea5e9',
        'card' => '#8b5cf6',
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $methods = app(DashboardMetricsService::class)->paymentMethods();

        return [
            'labels' => array_map(fn (string $method): string => str($method)->headline()->toString(), array_keys($methods)),
            'datasets' => [
                [
                    'data' => array_values($methods),
                    'backgroundColor' => array_map(fn (string $method): string => self::COLORS[$method] ?? '#9ca3af', array_keys($methods)),
                    'borderWidth' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '65%',
            'plugins' => ['legend' => ['position' => 'right']],
            'scales' => ['x' => ['display' => false], 'y' => ['display' => false]],
        ];
    }
}
