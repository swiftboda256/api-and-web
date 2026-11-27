<?php

namespace App\Notifications;

use App\Models\Communication;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The email channel of an admin Communication. Sent with notifyNow() from
 * SendCommunicationDeliveryJob, so a mail error lands in the delivery log.
 */
class CommunicationMailNotification extends Notification
{
    public function __construct(
        private readonly Communication $communication,
        private readonly ?string $firstName = null,
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
        $mail = (new MailMessage)
            ->subject($this->communication->title)
            ->greeting(filled($this->firstName) ? "Hello {$this->firstName}," : 'Hello,');

        foreach (preg_split('/\R{2,}/', trim($this->communication->body)) ?: [] as $paragraph) {
            $mail->line($paragraph);
        }

        return $mail;
    }
}
