<?php

namespace App\Services\Sms\Contracts;

interface SmsGateway
{
    public function send(string $to, string $message, ?string $senderId = null): bool;
}
