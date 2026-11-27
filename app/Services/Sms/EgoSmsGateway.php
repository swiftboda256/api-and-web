<?php

namespace App\Services\Sms;

use App\Services\Sms\Contracts\SmsGateway;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Wraps the Pahappa/EgoSMS JSON API.
 *
 * EgoSMS answers each send right away with {"Status":"Success"|"Failed","Message":"..."}
 * and has no delivery callbacks, so that answer is the final result. "Failed" is a
 * failure (Message = the reason), anything else a success. The raw response is logged.
 */
class EgoSmsGateway implements SmsGateway
{
    public function send(string $to, string $message, ?string $senderId = null): bool
    {
        return $this->deliver($to, $message, $senderId) === null;
    }

    public function deliver(string $to, string $message, ?string $senderId = null): ?string
    {
        $config = config('services.egosms');

        try {
            $response = Http::asJson()->timeout(20)->post($config['base_url'], [
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
        } catch (Throwable $e) {
            Log::error('egosms.send_failed', ['to' => $to, 'error' => $e->getMessage()]);

            return 'Could not reach EgoSMS: '.$e->getMessage();
        }

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

            return 'EgoSMS returned HTTP '.$response->status();
        }

        $status = (string) ($response->json('Status') ?? $response->json('status') ?? '');

        if (strtoupper($status) === 'FAILED') {
            Log::error('egosms.send_failed', [
                'to' => $to,
                'status' => $response->status(),
                'error' => $response->json('Message') ?? $response->body(),
            ]);

            return 'EgoSMS: '.($response->json('Message') ?? 'request failed');
        }

        return null;
    }
}
