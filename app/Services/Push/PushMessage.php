<?php

namespace App\Services\Push;

use Carbon\CarbonInterface;
use Kreait\Firebase\Messaging\AndroidConfig;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;

/**
 * One push in the shape both apps expect:
 *
 * - Android is DATA-ONLY (no top-level notification block) at high priority, so the app
 *   draws the notification itself with its own sounds and ringing.
 * - iOS gets an APNs alert with a sound (iOS won't reliably wake a closed app for a
 *   data-only push), or a background push when $silent.
 * - Every data value is a string, and data always carries type, id, title, body and
 *   sent_at (UTC ISO 8601).
 *
 * $id is the record the receiving app opens: the trip id on the rider app, the booking id
 * (trip_passenger_id / delivery_details_id) on the customer app.
 */
final readonly class PushMessage
{
    /**
     * @param  array<non-empty-string, string|int|float|null>  $data  extra data fields
     */
    public function __construct(
        public string $type,
        public int|string $id,
        public string $title = '',
        public string $body = '',
        public array $data = [],
        public string $sound = 'default',
        public ?string $interruptionLevel = null,
        public bool $silent = false,
        public ?int $ttlSeconds = null,
        public ?string $collapseKey = null,
        public ?CarbonInterface $expiresAt = null,
        public ?int $badge = null,
        public bool $contentAvailable = false,
    ) {}

    /**
     * @return array<non-empty-string, string>
     */
    public function payloadData(): array
    {
        $data = [
            ...$this->data,
            'type' => $this->type,
            'id' => (string) $this->id,
            'sent_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
        ];

        if (! $this->silent) {
            $data['title'] = $this->title;
            $data['body'] = $this->body;
        }

        if ($this->expiresAt !== null) {
            $data['expires_at'] = $this->expiresAt->copy()->utc()->format('Y-m-d\TH:i:s\Z');
        }

        if ($this->badge !== null) {
            $data['badge'] = (string) $this->badge;
        }

        return array_map(fn ($value): string => (string) $value, $data);
    }

    public function toCloudMessage(): CloudMessage
    {
        return CloudMessage::new()
            ->withData($this->payloadData())
            ->withAndroidConfig(AndroidConfig::fromArray($this->androidConfig()))
            ->withApnsConfig(ApnsConfig::fromArray($this->apnsConfig()));
    }

    /**
     * @return array<string, mixed>
     */
    private function androidConfig(): array
    {
        return array_filter([
            'priority' => 'high',
            'ttl' => $this->ttlSeconds !== null ? "{$this->ttlSeconds}s" : null,
            'collapse_key' => $this->collapseKey,
        ], fn ($value): bool => $value !== null);
    }

    /**
     * @return array<string, mixed>
     */
    private function apnsConfig(): array
    {
        if ($this->silent) {
            return [
                'headers' => [
                    'apns-priority' => '5',
                    'apns-push-type' => 'background',
                ],
                'payload' => ['aps' => ['content-available' => 1]],
            ];
        }

        $aps = array_filter([
            'alert' => ['title' => $this->title, 'body' => $this->body],
            'sound' => $this->sound,
            'badge' => $this->badge,
            'interruption-level' => $this->interruptionLevel,
        ], fn ($value): bool => $value !== null);

        $headers = array_filter([
            'apns-priority' => '10',
            'apns-push-type' => 'alert',
            'apns-expiration' => $this->expiresAt !== null ? (string) $this->expiresAt->getTimestamp() : null,
            'apns-collapse-id' => $this->collapseKey,
        ], fn ($value): bool => $value !== null);

        // Also wakes the app in the background, e.g. so a job offer starts ringing at once.
        if ($this->contentAvailable) {
            $aps['content-available'] = 1;
        }

        return ['headers' => $headers, 'payload' => ['aps' => $aps]];
    }
}
