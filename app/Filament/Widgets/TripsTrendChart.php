<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

class TripsTrendChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 7;

    protected ?string $heading = 'Trips';

    protected ?string $description = 'Completed vs cancelled, last 30 days';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = app(DashboardMetricsService::class)->tripsTrend();

        return [
            'labels' => $trend['labels'],
            'datasets' => [
                [
                    'label' => 'Completed',
                    'data' => $trend['completed'],
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Cancelled',
                    'data' => $trend['cancelled'],
                    'borderColor' => '#f43f5e',
                    'backgroundColor' => 'rgba(244, 63, 94, 0.08)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                ],
            ],
        ];
    }

    protected function getOptions(): array
    {
        return [
            'interaction' => ['intersect' => false, 'mode' => 'index'],
            'plugins' => ['legend' => ['position' => 'bottom']],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
