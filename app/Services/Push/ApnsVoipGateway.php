<?php

namespace App\Services\Push;

use App\Models\User;
use App\Models\UserDevice;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rings a CallKit call screen on iOS even when the app is swiped away or suspended --
 * the one thing FCM can't do. Sent straight to Apple (APNs), with the same .p8 key
 * uploaded to Firebase. See docs/new_IOS_PUSH_BACKEND_GUIDE.md §10.
 *
 * Ringing events only (§10.2): sending a VoIP push for anything else gets the app's
 * PushKit entitlement revoked by Apple, since every VoIP push must show a call.
 */
class ApnsVoipGateway
{
    public const array RINGING_TYPES = [
        'ride_offer',
        'ride_share_join_request',
        'posted_ride_booking_request',
        'trip_rider_arrived',
        'trip_near_destination',
    ];

    public function sendRingingPush(User $user, UserDevice $device, PushMessage $message): void
    {
        if ($device->voip_token === null || ! in_array($message->type, self::RINGING_TYPES, true)) {
            return;
        }

        $jwt = $this->signedJwt();

        if ($jwt === null) {
            return;
        }

        $environment = app()->isProduction() ? 'production' : 'development';
        $app = $user->hasRole('rider') ? 'rider' : 'passenger';
        $bundleId = config("apns.bundles.{$app}.{$environment}");
        $host = config("apns.hosts.{$environment}");

        try {
            $response = Http::withOptions(['version' => 2.0])
                ->withHeaders([
                    'apns-topic' => "{$bundleId}.voip",
                    'apns-push-type' => 'voip',
                    'apns-priority' => '10',
                    'apns-expiration' => '0',
                    'authorization' => "bearer {$jwt}",
                ])
                ->post("https://{$host}/3/device/{$device->voip_token}", [
                    'aps' => (object) [],
                    ...$message->payloadData(),
                ]);
        } catch (Throwable $e) {
            Log::error('apns_voip.send_failed', ['type' => $message->type, 'error' => $e->getMessage()]);

            return;
        }

        if ($response->successful()) {
            return;
        }

        $reason = $response->json('reason');

        if ($response->status() === 410 || $reason === 'BadDeviceToken') {
            $device->update(['voip_token' => null]);

            return;
        }

        Log::warning('apns_voip.send_failure', [
            'type' => $message->type,
            'status' => $response->status(),
            'reason' => $reason,
        ]);
    }

    /**
     * APNs auth tokens are valid up to an hour; cached well under that so it's always
     * refreshed before expiry. Returns null (and sends nothing) if the key isn't configured
     * -- e.g. in dev/test environments without real Apple credentials.
     */
    private function signedJwt(): ?string
    {
        $authKeyPath = config('apns.auth_key_path');
        $keyId = config('apns.key_id');
        $teamId = config('apns.team_id');

        if (! $authKeyPath || ! $keyId || ! $teamId) {
            return null;
        }

        $authKey = is_file($authKeyPath) ? file_get_contents($authKeyPath) : false;

        if ($authKey === false) {
            Log::warning('apns_voip.auth_key_unreadable', ['path' => $authKeyPath]);

            return null;
        }

        return Cache::remember('apns_voip_jwt', 2700, fn (): string => JWT::encode(
            ['iss' => $teamId, 'iat' => time()],
            $authKey,
            'ES256',
            $keyId,
        ));
    }
}
