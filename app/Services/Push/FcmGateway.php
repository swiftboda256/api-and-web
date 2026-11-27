<?php

namespace App\Services\Push;

use App\Jobs\SendPushJob;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\PushEventNotification;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\NotFound;
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
        private readonly ApnsVoipGateway $voip,
    ) {}

    /**
     * Adds the push to the user's in-app list and queues its delivery, once any open
     * transaction commits.
     */
    public function sendToUser(?User $user, PushMessage $message): void
    {
        if ($user === null) {
            return;
        }

        if ($message->persist && ! $message->silent) {
            $user->notify(new PushEventNotification($message));
        }

        SendPushJob::dispatch($user->id, $message)
            ->onQueue(config('queue.push_queue'))
            ->afterCommit();
    }

    /**
     * Sends to the user's active device now (called by SendPushJob). A user has at most
     * one: logging in on another device deactivates the others.
     */
    public function deliver(User $user, PushMessage $message): void
    {
        $this->deliverNow($user, $message);
    }

    /**
     * Same as deliver(), but says how it went: null when FCM accepted the push, otherwise
     * why it didn't go out (used by the admin Communications log).
     */
    public function deliverNow(User $user, PushMessage $message): ?string
    {
        // Most pushes don't set their own badge -- default it to the recipient's unread
        // count (now including anything just persisted above). A silent push has no alert
        // to badge, and some pushes (e.g. a ride offer) carry a more specific count already.
        if ($message->badge === null && ! $message->silent) {
            $message = $message->withBadge($user->unreadNotifications()->count());
        }

        $device = $this->activeDevice($user);

        if ($device === null) {
            return 'No active device with an FCM token';
        }

        // On a ringing event, a device with a VoIP token also gets rung there -- the only
        // way to show a CallKit call once the app is swiped away. The FCM alert then drops
        // its own sound so the two pushes don't double-ring (see ApnsVoipGateway).
        $ringsOnVoip = $device->voip_token !== null && in_array($message->type, ApnsVoipGateway::RINGING_TYPES, true);

        if ($ringsOnVoip) {
            $this->voip->sendRingingPush($user, $device, $message);
        }

        $failures = $this->sendToTokens([(string) $device->fcm_token], $ringsOnVoip ? $message->withoutSound() : $message);

        return $failures === [] ? null : (string) reset($failures);
    }

    /**
     * The device a push goes to: a user has at most one active one (logging in on another
     * device deactivates the others).
     */
    public function activeDevice(User $user): ?UserDevice
    {
        return $user->devices()
            ->where('active', true)
            ->whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->latest('last_seen_at')
            ->first();
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, string> the tokens FCM didn't take, with the reason ([] = all sent)
     */
    public function sendToTokens(array $tokens, PushMessage $message): array
    {
        if ($tokens === []) {
            return [];
        }

        try {
            $report = $this->messaging->sendMulticast($message->toCloudMessage(), $tokens);
        } catch (Throwable $e) {
            Log::error('fcm.send_failed', ['type' => $message->type, 'id' => (string) $message->id, 'error' => $e->getMessage()]);

            return array_fill_keys($tokens, 'FCM error: '.$e->getMessage());
        }

        if (! $report->hasFailures()) {
            return [];
        }

        $failures = $report->failures()->getItems();

        Log::warning('fcm.send_failures', [
            'type' => $message->type,
            'failures' => collect($failures)->map(fn (SendReport $item): array => [
                'target' => $item->target()->value(),
                'error' => $item->error()?->getMessage(),
            ])->all(),
        ]);

        // The token is gone for good (uninstalled/unregistered) or was never valid: stop
        // sending to it. A device row still gets reactivated the next time it logs in.
        $deadTokens = collect($failures)
            ->filter(fn (SendReport $item): bool => $item->error() instanceof NotFound || $item->error() instanceof InvalidArgument)
            ->map(fn (SendReport $item): string => $item->target()->value())
            ->all();

        if ($deadTokens !== []) {
            UserDevice::query()->whereIn('fcm_token', $deadTokens)->update(['active' => false]);
        }

        return collect($failures)
            ->mapWithKeys(fn (SendReport $item): array => [
                $item->target()->value() => $item->error() instanceof NotFound
                    ? 'FCM token no longer registered (app uninstalled or logged out)'
                    : 'FCM rejected the push: '.($item->error()?->getMessage() ?? 'unknown error'),
            ])
            ->all();
    }
}
