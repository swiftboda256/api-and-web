<?php

namespace App\Jobs;

use App\Models\TripOffer;
use App\Models\User;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Delivers one push off the request/scheduler (see FcmGateway::sendToUser). Never retried:
 * a late duplicate would ring the device twice, and the gateway already logs failures.
 */
class SendPushJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public int $userId,
        public PushMessage $message,
    ) {}

    public function handle(FcmGateway $push): void
    {
        // Waited in the queue past its ring window.
        if ($this->message->expiresAt?->isPast()) {
            return;
        }

        // Taken, cancelled or expired while queued: ringing now would outlive the
        // ride_offer_cancelled push that stops it.
        if ($this->message->tripOfferId !== null
            && ! TripOffer::query()->whereKey($this->message->tripOfferId)->where('status', 'pending')->exists()) {
            return;
        }

        $user = User::query()->find($this->userId);

        if ($user !== null) {
            $push->deliver($user, $this->message);
        }
    }
}
