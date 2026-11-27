<?php

namespace App\Services\Communication;

use App\Jobs\DispatchCommunicationJob;
use App\Models\Communication;
use App\Models\CommunicationDelivery;
use App\Models\Configuration;
use App\Models\User;
use App\Models\UserDevice;
use App\Notifications\CommunicationMailNotification;
use App\Notifications\PushEventNotification;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use App\Services\Sms\Contracts\SmsGateway;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Admin broadcasts (Admin → Communications): who a message reaches on each channel, what
 * the SMS part costs, and sending it with one CommunicationDelivery log row per recipient
 * and channel. A recipient a channel can't reach (no FCM token / email / phone) gets a
 * failed row with the reason straight away; the rest are sent one queued job each.
 */
class CommunicationService
{
    public const string PUSH_TYPE = 'announcement';

    public const int DEFAULT_SMS_COST = 35;

    public const string NO_PUSH_DEVICE = 'No active device with an FCM token';

    public const string NO_EMAIL = 'No email address on the account';

    public const string INVALID_EMAIL = 'Invalid email address';

    public const string NO_PHONE = 'No phone number on the account';

    public function __construct(
        private readonly FcmGateway $push,
        private readonly SmsGateway $sms,
    ) {}

    /**
     * Everyone with one of $roles (active accounts only), plus the users picked by hand
     * (any status but deleted).
     *
     * @param  list<string>  $roles
     * @param  list<int|string>  $userIds
     * @return Builder<User>
     */
    public function audience(array $roles, array $userIds): Builder
    {
        $query = User::query()->where('status', '!=', 'deleted');

        if ($roles === [] && $userIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($roles, $userIds): void {
            if ($roles !== []) {
                $query->orWhere(fn (Builder $query) => $query->role($roles)->where('status', 'active'));
            }

            if ($userIds !== []) {
                $query->orWhereIn('id', $userIds);
            }
        });
    }

    /**
     * Recipient counts per channel and the SMS cost, for the compose form.
     *
     * @param  list<string>  $roles
     * @param  list<int|string>  $userIds
     * @param  list<string>  $channels
     * @return array{recipients: int, reachable: array<string, int>, sms_segments: int, sms_unit_cost: int, sms_cost: int, sms_unicode: bool}
     */
    public function preview(array $roles, array $userIds, array $channels, string $body): array
    {
        $audience = $this->audience($roles, $userIds);
        $reachable = [];

        foreach ($channels as $channel) {
            $reachable[$channel] = $this->reachableOn(clone $audience, $channel)->count();
        }

        $segments = in_array(Communication::CHANNEL_SMS, $channels, true) ? SmsSegments::count($body) : 0;
        $unitCost = $this->smsUnitCost();

        return [
            'recipients' => (clone $audience)->count(),
            'reachable' => $reachable,
            'sms_segments' => $segments,
            'sms_unit_cost' => $unitCost,
            'sms_cost' => ($reachable[Communication::CHANNEL_SMS] ?? 0) * $segments * $unitCost,
            'sms_unicode' => SmsSegments::isUnicode($body),
        ];
    }

    /**
     * Logs a delivery row for every recipient on every channel and queues the sends.
     *
     * With SMS, $data['sms_cost_acknowledged_amount'] must be the cost the sender confirmed,
     * and it must still be the cost now; it's stored with who confirmed it and when.
     *
     * @param  array{title: string, body: string, channels: list<string>, target_roles?: list<string>|null, target_user_ids?: list<int|string>|null, sms_cost_acknowledged_amount?: int|string|null}  $data
     *
     * @throws SmsCostNotAcknowledged
     */
    public function send(array $data, ?User $sender = null): Communication
    {
        $channels = array_values(array_intersect(array_keys(Communication::CHANNELS), $data['channels']));
        $roles = $data['target_roles'] ?? [];
        $userIds = array_map('intval', $data['target_user_ids'] ?? []);
        $preview = $this->preview($roles, $userIds, $channels, $data['body']);
        $sender ??= Auth::user();

        $acknowledgement = [];

        if (in_array(Communication::CHANNEL_SMS, $channels, true)) {
            $acknowledged = $data['sms_cost_acknowledged_amount'] ?? null;

            if (! is_numeric($acknowledged) || (int) $acknowledged !== $preview['sms_cost']) {
                throw new SmsCostNotAcknowledged($preview['sms_cost']);
            }

            $acknowledgement = [
                'sms_cost_acknowledged_by' => $sender?->getKey(),
                'sms_cost_acknowledged_at' => now(),
                'sms_cost_acknowledged_amount' => (int) $acknowledged,
            ];
        }

        $communication = DB::transaction(function () use ($data, $channels, $roles, $userIds, $preview, $acknowledgement): Communication {
            $communication = Communication::query()->create([
                'title' => $data['title'],
                'body' => $data['body'],
                'channels' => $channels,
                'target_roles' => $roles ?: null,
                'target_user_ids' => $userIds ?: null,
                'status' => Communication::STATUS_QUEUED,
                'recipients_count' => $preview['recipients'],
                'sms_segments' => $preview['sms_segments'],
                'sms_unit_cost' => $preview['sms_unit_cost'],
                'sms_estimated_cost' => $preview['sms_cost'],
                ...$acknowledgement,
            ]);

            $this->audience($roles, $userIds)
                ->select(['id', 'email', 'phone'])
                ->addSelect(['push_device_type' => UserDevice::query()
                    ->select('device_type')
                    ->whereColumn('user_id', 'users.id')
                    ->where('active', true)
                    ->whereNotNull('fcm_token')
                    ->where('fcm_token', '!=', '')
                    ->latest('last_seen_at')
                    ->limit(1),
                ])
                ->chunkById(500, function (Collection $users) use ($communication, $channels): void {
                    $now = now();
                    $rows = [];

                    foreach ($users as $user) {
                        foreach ($channels as $channel) {
                            [$destination, $failure] = $this->destination($user, $channel);

                            $rows[] = [
                                'communication_id' => $communication->id,
                                'user_id' => $user->id,
                                'channel' => $channel,
                                'destination' => $destination,
                                'status' => $failure === null ? CommunicationDelivery::STATUS_PENDING : CommunicationDelivery::STATUS_FAILED,
                                'failure_reason' => $failure,
                                'created_at' => $now,
                                'updated_at' => $now,
                            ];
                        }
                    }

                    CommunicationDelivery::query()->insert($rows);
                });

            return $communication;
        });

        DispatchCommunicationJob::dispatch($communication->id)->onQueue(self::queue());

        return $communication;
    }

    /**
     * Sends one logged delivery (SendCommunicationDeliveryJob) and records the outcome.
     */
    public function deliver(CommunicationDelivery $delivery): void
    {
        if ($delivery->status !== CommunicationDelivery::STATUS_PENDING) {
            return;
        }

        $communication = $delivery->communication;
        $user = $delivery->user;

        try {
            $failure = match (true) {
                $communication === null => 'The communication was deleted',
                $user === null => 'The user account no longer exists',
                default => $this->sendOn($delivery->channel, $communication, $user, $delivery),
            };
        } catch (Throwable $e) {
            $failure = $e->getMessage();
        }

        $this->record($delivery, $failure);
    }

    /**
     * Marks a delivery sent (null) or failed with $failure, and closes the communication
     * once nothing is left pending.
     */
    public function record(CommunicationDelivery $delivery, ?string $failure): void
    {
        $delivery->forceFill([
            'status' => $failure === null ? CommunicationDelivery::STATUS_SENT : CommunicationDelivery::STATUS_FAILED,
            'failure_reason' => $failure === null ? null : mb_substr($failure, 0, 1000),
            'sent_at' => $failure === null ? now() : null,
        ])->save();

        $this->completeIfDone($delivery->communication_id);
    }

    public function completeIfDone(int $communicationId): void
    {
        $pending = CommunicationDelivery::query()
            ->where('communication_id', $communicationId)
            ->where('status', CommunicationDelivery::STATUS_PENDING)
            ->exists();

        if (! $pending) {
            Communication::query()
                ->whereKey($communicationId)
                ->where('status', '!=', Communication::STATUS_COMPLETED)
                ->update(['status' => Communication::STATUS_COMPLETED, 'completed_at' => now()]);
        }
    }

    /**
     * UGX per SMS part, from the sms_cost_per_sms configuration (System → Configurations).
     */
    public function smsUnitCost(): int
    {
        $value = Configuration::get('sms_cost_per_sms', self::DEFAULT_SMS_COST);

        return is_numeric($value) && (int) $value >= 0 ? (int) $value : self::DEFAULT_SMS_COST;
    }

    /**
     * Communications go out on their own queue so a big broadcast doesn't hold up ride
     * offers on the push queue. Null = the connection's default queue.
     */
    public static function queue(): ?string
    {
        return config('queue.communications_queue');
    }

    /**
     * @param  Builder<User>  $audience
     * @return Builder<User>
     */
    private function reachableOn(Builder $audience, string $channel): Builder
    {
        return match ($channel) {
            Communication::CHANNEL_PUSH => $audience->whereHas('devices', fn (Builder $query) => $query
                ->where('active', true)
                ->whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')),
            Communication::CHANNEL_EMAIL => $audience->whereNotNull('email')->where('email', '!=', ''),
            Communication::CHANNEL_SMS => $audience->whereNotNull('phone')->where('phone', '!=', ''),
            default => $audience->whereRaw('1 = 0'),
        };
    }

    /**
     * Where this channel goes for this user, or why it can't.
     *
     * @return array{0: string|null, 1: string|null} [destination, failure reason]
     */
    private function destination(User $user, string $channel): array
    {
        return match ($channel) {
            Communication::CHANNEL_PUSH => $user->getAttribute('push_device_type') === null
                ? [null, self::NO_PUSH_DEVICE]
                : [ucfirst((string) $user->getAttribute('push_device_type')).' device', null],
            Communication::CHANNEL_EMAIL => match (true) {
                blank($user->email) => [null, self::NO_EMAIL],
                filter_var($user->email, FILTER_VALIDATE_EMAIL) === false => [$user->email, self::INVALID_EMAIL],
                default => [$user->email, null],
            },
            Communication::CHANNEL_SMS => blank($user->phone) ? [null, self::NO_PHONE] : [$user->phone, null],
            default => [null, 'Unknown channel'],
        };
    }

    /**
     * @return string|null null when the provider accepted it, else why not
     */
    private function sendOn(string $channel, Communication $communication, User $user, CommunicationDelivery $delivery): ?string
    {
        return match ($channel) {
            Communication::CHANNEL_PUSH => $this->sendPush($communication, $user),
            Communication::CHANNEL_EMAIL => $this->sendEmail($communication, $user, $delivery),
            Communication::CHANNEL_SMS => $this->sms->deliver((string) $delivery->destination, $communication->body),
            default => 'Unknown channel',
        };
    }

    private function sendPush(Communication $communication, User $user): ?string
    {
        // The token may have gone since the log row was written (logout / new device).
        if ($this->push->activeDevice($user) === null) {
            return self::NO_PUSH_DEVICE;
        }

        $message = new PushMessage(
            type: self::PUSH_TYPE,
            id: $communication->id,
            title: $communication->title,
            body: $communication->body,
        );

        // Also lands in the app's notification list, like every other persisted push.
        $user->notify(new PushEventNotification($message));

        return $this->push->deliverNow($user, $message);
    }

    /**
     * Sent synchronously: a mail transport error throws, and deliver() logs it as the reason.
     */
    private function sendEmail(Communication $communication, User $user, CommunicationDelivery $delivery): null
    {
        Notification::route('mail', (string) $delivery->destination)
            ->notifyNow(new CommunicationMailNotification($communication, $user->first_name));

        return null;
    }
}
