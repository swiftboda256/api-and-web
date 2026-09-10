<?php

namespace App\Notifications;

use App\Models\UserDeleteRequest;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountDeletionRequestedNotification extends Notification
{
    public function __construct(
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
            ->subject('Your Swift Boda account deletion request')
            ->line('We received a request to delete your Swift Boda account.')
            ->line('Your account and all associated data are being permanently deleted now.')
            ->line("If you didn't request this, please contact support right away.");
    }
}
