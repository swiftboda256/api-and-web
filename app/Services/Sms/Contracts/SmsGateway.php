<?php

namespace App\Services\Sms\Contracts;

interface SmsGateway
{
    public function send(string $to, string $message, ?string $senderId = null): bool;

    /**
     * Same as send(), but says why it failed: null when the gateway accepted the SMS.
     */
    public function deliver(string $to, string $message, ?string $senderId = null): ?string;
}
