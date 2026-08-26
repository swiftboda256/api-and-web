<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\SetPasswordNotification;
use App\Services\Sms\Contracts\SmsGateway;
use Illuminate\Support\Facades\URL;

readonly class SetPasswordLinkService
{
    private const int EXPIRY_HOURS = 24;

    public function __construct(
        private SmsGateway $smsGateway,
    ) {}

    public function send(User $user, string $channel): void
    {
        $url = URL::temporarySignedRoute(
            'set-password.show',
            now()->addHours(self::EXPIRY_HOURS),
            ['user' => $user->id],
        );

        if ($channel === 'sms') {
            $this->smsGateway->send($user->phone, "Welcome to Swift Boda. Set your password here: {$url}");

            return;
        }

        $user->notify(new SetPasswordNotification($url));
    }
}
