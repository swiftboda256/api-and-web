<?php

namespace App\Jobs;

use App\Models\CommunicationDelivery;
use App\Services\Communication\CommunicationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Sends one recipient × channel of an admin Communication. Never retried -- a retry after
 * a timeout could send (and bill) the same SMS twice; the failure goes in the log instead.
 */
class SendCommunicationDeliveryJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(
        public int $deliveryId,
    ) {}

    public function handle(CommunicationService $communications): void
    {
        $delivery = CommunicationDelivery::query()->with(['communication', 'user'])->find($this->deliveryId);

        if ($delivery !== null) {
            $communications->deliver($delivery);
        }
    }

    public function failed(?Throwable $e): void
    {
        $delivery = CommunicationDelivery::query()->find($this->deliveryId);

        if ($delivery?->status === CommunicationDelivery::STATUS_PENDING) {
            app(CommunicationService::class)->record($delivery, 'Send job failed: '.($e?->getMessage() ?? 'unknown error'));
        }
    }
}
