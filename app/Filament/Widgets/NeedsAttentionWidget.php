<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Kyc\KycReviews;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Services\Dashboard\DashboardMetricsService;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;

/**
 * Work queues for admins. Items link to a filtered list where an admin page exists;
 * support tickets and delete requests have no admin page yet, so they're counts only.
 */
class NeedsAttentionWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.needs-attention-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $counts = app(DashboardMetricsService::class)->attention();

        $transactions = TransactionResource::canViewAny();

        $items = [
            [
                'label' => 'Riders awaiting KYC review',
                'count' => $counts['kyc_review'],
                'icon' => Heroicon::OutlinedIdentification,
                'tone' => 'warning',
                'url' => KycReviews::canAccess() ? KycReviews::getUrl(['tab' => 'awaiting']) : null,
            ],
            [
                'label' => 'Documents awaiting approval',
                'count' => $counts['documents'],
                'icon' => Heroicon::OutlinedDocumentCheck,
                'tone' => 'warning',
                'url' => KycReviews::canAccess() ? KycReviews::getUrl(['tab' => 'awaiting']) : null,
            ],
            [
                'label' => 'Withdrawals stuck over 10 min',
                'count' => $counts['stuck_withdrawals'],
                'icon' => Heroicon::OutlinedArrowUpTray,
                'tone' => 'danger',
                'url' => $transactions ? TransactionResource::getUrl('index', ['filters' => [
                    'status' => ['value' => 'pending'],
                    'transaction_type' => ['values' => ['withdrawal']],
                ]]) : null,
            ],
            [
                'label' => 'Transactions pending over 10 min',
                'count' => $counts['stuck_transactions'],
                'icon' => Heroicon::OutlinedClock,
                'tone' => 'danger',
                'url' => $transactions ? TransactionResource::getUrl('index', ['filters' => ['status' => ['value' => 'pending']]]) : null,
            ],
            [
                'label' => 'Failed transactions today',
                'count' => $counts['failed_transactions'],
                'icon' => Heroicon::OutlinedXCircle,
                'tone' => 'danger',
                'url' => $transactions ? TransactionResource::getUrl('index', ['filters' => [
                    'status' => ['value' => 'failed'],
                    'created_at' => ['from' => now()->toDateString(), 'until' => now()->toDateString()],
                ]]) : null,
            ],
            [
                'label' => 'Open support tickets',
                'count' => $counts['open_tickets'],
                'icon' => Heroicon::OutlinedLifebuoy,
                'tone' => $counts['urgent_tickets'] > 0 ? 'danger' : 'warning',
                'hint' => $counts['urgent_tickets'] > 0 ? "{$counts['urgent_tickets']} high or urgent" : null,
                'url' => null,
            ],
            [
                'label' => 'Account delete requests',
                'count' => $counts['delete_requests'],
                'icon' => Heroicon::OutlinedUserMinus,
                'tone' => 'warning',
                'url' => null,
            ],
        ];

        return [
            'items' => $items,
            'allClear' => collect($items)->sum('count') === 0,
        ];
    }
}
