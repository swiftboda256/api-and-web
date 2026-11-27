<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class TripTypesChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 9;

    protected ?string $heading = 'Trips by type';

    protected ?string $description = 'Requested in the last 30 days';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '240px';

    private const COLORS = [
        'ride' => '#f59e0b',
        'ride_share' => '#10b981',
        'delivery' => '#0ea5e9',
        'delivery_share' => '#8b5cf6',
        'posted_ride' => '#f43f5e',
    ];

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $types = app(DashboardMetricsService::class)->tripTypes();

        return [
            'labels' => array_map(fn (string $type): string => str($type)->headline()->toString(), array_keys($types)),
            'datasets' => [
                [
                    'data' => array_values($types),
                    'backgroundColor' => array_map(fn (string $type): string => self::COLORS[$type] ?? '#9ca3af', array_keys($types)),
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
