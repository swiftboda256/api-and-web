<?php

namespace App\Notifications;

use App\Services\Push\PushMessage;
use Illuminate\Notifications\Notification;

/**
 * Mirrors a PushMessage into the notifiable's database notifications, so every push that
 * opts in (PushMessage::$persist) also shows up in the in-app notification list -- the
 * stored type matches the push's own "type" (e.g. "trip_completed") instead of this class
 * name, so NotificationService's type filter still works.
 */
class PushEventNotification extends Notification
{
    public function __construct(
        private readonly PushMessage $message,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function databaseType(object $notifiable): string
    {
        return $this->message->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'id' => $this->message->id,
            'title' => $this->message->title,
            'body' => $this->message->body,
            ...$this->message->data,
        ];
    }
}
