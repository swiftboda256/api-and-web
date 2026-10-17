<?php

namespace App\Services\Push;

use App\Models\User;
use App\Notifications\PushEventNotification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\SendReport;
use Throwable;

/**
 * Sends pushes over FCM HTTP v1. A failed push is logged, never thrown -- a notification
 * must not break the trip/payment action that triggered it.
 */
class FcmGateway
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    /**
     * Sends to the user's active device. A user has at most one: logging in on another
     * device deactivates the others.
     */
    public function sendToUser(?User $user, PushMessage $message): void
    {
        if ($user !== null && $message->persist && ! $message->silent) {
            $user->notify(new PushEventNotification($message));
        }

        $token = $user?->devices()
            ->where('active', true)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->latest('last_seen_at')
            ->value('fcm_token');

        if ($token !== null) {
            $this->sendToTokens([(string) $token], $message);
        }
    }

    /**
     * @param  list<string>  $tokens
     */
    public function sendToTokens(array $tokens, PushMessage $message): void
    {
        if ($tokens === []) {
            return;
        }

        try {
            $report = $this->messaging->sendMulticast($message->toCloudMessage(), $tokens);
        } catch (Throwable $e) {
            Log::error('fcm.send_failed', ['type' => $message->type, 'id' => (string) $message->id, 'error' => $e->getMessage()]);

            return;
        }

        if ($report->hasFailures()) {
            Log::warning('fcm.send_failures', [
                'type' => $message->type,
                'failures' => collect($report->failures()->getItems())->map(fn (SendReport $item): array => [
                    'target' => $item->target()->value(),
                    'error' => $item->error()?->getMessage(),
                ])->all(),
            ]);
        }
    }
}
