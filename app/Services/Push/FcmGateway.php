<?php

namespace App\Services\Push;

use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Messaging\SendReport;

class FcmGateway
{
    public function __construct(
        private readonly Messaging $messaging,
    ) {}

    /**
     * @param  list<string>  $tokens
     * @param  array<non-empty-string, string>  $data
     */
    public function sendToTokens(array $tokens, string $title, string $body, array $data = []): void
    {
        if ($tokens === []) {
            return;
        }

        $message = CloudMessage::new()
            ->withNotification(Notification::create($title, $body))
            ->withData($data);

        $report = $this->messaging->sendMulticast($message, $tokens);

        if ($report->hasFailures()) {
            Log::warning('fcm.send_failures', [
                'failures' => collect($report->failures()->getItems())->map(fn (SendReport $item): array => [
                    'target' => $item->target()->value(),
                    'error' => $item->error()?->getMessage(),
                ])->all(),
            ]);
        }
    }
}
