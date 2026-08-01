<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserCredentialsNotification extends Notification
{
    public function __construct(
        private readonly string $password,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Swift Boda account has been created')
            ->line('An account has been created for you on Swift Boda.')
            ->line("Email: {$notifiable->email}")
            ->line("Temporary password: {$this->password}")
            ->line('Please log in and change your password as soon as possible.');
    }
}
