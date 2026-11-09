<?php

namespace App\Events;

use App\Http\Resources\Api\V1\UserApp\ChatMessageResource;
use App\Models\ChatMessage;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

final class ChatMessageSent implements ShouldBroadcastNow
{
    public function __construct(
        public readonly ChatMessage $message,
    ) {}

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("chat-room.{$this->message->chat_room_id}")];
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return (new ChatMessageResource($this->message))->resolve();
    }
}
