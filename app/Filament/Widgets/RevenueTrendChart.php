<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;

/**
 * Gross fares (what customers paid on dropped-off bookings) vs commission (the platform's share).
 */
class RevenueTrendChart extends ChartWidget
{
    use HasWidgetShield;

    protected static ?int $sort = 8;

    protected ?string $heading = 'Revenue (UGX)';

    protected ?string $description = 'Gross fares vs commission, last 30 days';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $trend = app(DashboardMetricsService::class)->revenueTrend();

        return [
            'labels' => $trend['labels'],
            'datasets' => [
                [
                    'label' => 'Gross fares',
                    'data' => $trend['gross'],
                    'borderColor' => '#f59e0b',
                    'backgroundColor' => 'rgba(245, 158, 11, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 0,
                ],
                [
                    'label' => 'Commission',
                    'data' => $trend['commission'],
                    'borderColor' => '#8b5cf6',
                    'backgroundColor' => 'rgba(139, 92, 246, 0.10)',
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
            'scales' => ['y' => ['beginAtZero' => true]],
        ];
    }
}
