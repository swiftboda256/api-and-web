<?php

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wraps the Pahappa/EgoSMS JSON API.
 *
 * Response shape confirmed against the live endpoint (with invalid/blank
 * credentials): {"Status":"Failed","Message":"..."}. The success shape
 * hasn't been observed yet (needs real credentials), so a missing/failed
 * "Status" is treated as authoritative failure, anything else as success.
 * The raw response is always logged so this can be tightened further once
 * a real success response has been seen.
 */
class EgoSmsGateway implements SmsGateway
{
    public function send(string $to, string $message, ?string $senderId = null): bool
    {
        $config = config('services.egosms');

        $response = Http::asJson()->post($config['base_url'], [
            'method' => 'SendSms',
            'userdata' => [
                'username' => $config['username'],
                'password' => $config['password'],
            ],
            'msgdata' => [
                [
                    'number' => $to,
                    'message' => $message,
                    'senderid' => $senderId ?? $config['sender_id'],
                    'priority' => $config['priority'],
                ],
            ],
        ]);

        Log::info('egosms.response', [
            'to' => $to,
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        if ($response->failed()) {
            Log::error('egosms.send_failed', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $response->body(),
            ]);

            return false;
        }

        $status = (string) ($response->json('Status') ?? $response->json('status') ?? '');

        if (strtoupper($status) === 'FAILED') {
            Log::error('egosms.send_failed', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $response->json('Message') ?? $response->body(),
            ]);

            return false;
        }

        return true;
    }
}
