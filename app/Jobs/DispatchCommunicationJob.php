<?php

namespace App\Jobs;

use App\Models\Communication;
use App\Models\CommunicationDelivery;
use App\Services\Communication\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Collection;

/**
 * Fans an admin Communication out into one SendCommunicationDeliveryJob per pending log
 * row, so a single slow SMS/email can't hold the rest past the queue's retry_after.
 */
class DispatchCommunicationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 80;

    public function __construct(
        public int $communicationId,
    ) {}

    public function handle(CommunicationService $communications): void
    {
        Communication::query()
            ->whereKey($this->communicationId)
            ->where('status', Communication::STATUS_QUEUED)
            ->update(['status' => Communication::STATUS_SENDING]);

        CommunicationDelivery::query()
            ->where('communication_id', $this->communicationId)
            ->where('status', CommunicationDelivery::STATUS_PENDING)
            ->select('id')
            ->chunkById(500, function (Collection $deliveries): void {
                foreach ($deliveries as $delivery) {
                    SendCommunicationDeliveryJob::dispatch($delivery->id)->onQueue(CommunicationService::queue());
                }
            });

        // Everyone may have been unreachable already.
        $communications->completeIfDone($this->communicationId);
    }
}
