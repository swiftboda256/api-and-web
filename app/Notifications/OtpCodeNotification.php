<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OtpCodeNotification extends Notification
{
    public function __construct(
        private readonly string $code,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Swift Boda verification code')
            ->line("Your verification code is: {$this->code}")
            ->line('This code expires in '.config('otp.expiry_minutes').' minutes.')
            ->line("If you didn't request this code, you can safely ignore this email.");
    }
}
