<?php

namespace App\Filament\Resources\Communications\Widgets;

use App\Models\Communication;
use App\Models\CommunicationDelivery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;

/**
 * Sent / failed / pending per channel on a communication's page (refreshes while sending).
 */
class CommunicationDeliveryStats extends StatsOverviewWidget
{
    public ?Model $record = null;

    protected ?string $pollingInterval = '10s';

    protected function getStats(): array
    {
        if (! $this->record instanceof Communication) {
            return [];
        }

        $communication = $this->record;

        /** @var array<string, array<string, int>> $counts channel => status => count */
        $counts = [];

        CommunicationDelivery::query()
            ->where('communication_id', $communication->id)
            ->selectRaw('channel, status, count(*) as total')
            ->groupBy('channel', 'status')
            ->get()
            ->each(function (CommunicationDelivery $row) use (&$counts): void {
                $counts[$row->channel][$row->status] = (int) $row->getAttribute('total');
            });

        $stats = [];

        foreach ($communication->channels as $channel) {
            $sent = $counts[$channel][CommunicationDelivery::STATUS_SENT] ?? 0;
            $failed = $counts[$channel][CommunicationDelivery::STATUS_FAILED] ?? 0;
            $pending = $counts[$channel][CommunicationDelivery::STATUS_PENDING] ?? 0;
            $total = $sent + $failed + $pending;

            $stats[] = Stat::make(Communication::CHANNELS[$channel] ?? $channel, Number::format($sent).' / '.Number::format($total).' sent')
                ->description(Number::format($failed).' failed'.($pending > 0 ? ' · '.Number::format($pending).' pending' : ''))
                ->color($failed > 0 ? 'danger' : 'success');

            if ($channel === Communication::CHANNEL_SMS) {
                $stats[] = Stat::make('SMS cost', 'UGX '.Number::format($sent * $communication->sms_segments * $communication->sms_unit_cost))
                    ->description('For the '.Number::format($sent).' SMS sent (estimate was UGX '.Number::format($communication->sms_estimated_cost).')')
                    ->color('warning');
            }
        }

        return $stats;
    }
}
