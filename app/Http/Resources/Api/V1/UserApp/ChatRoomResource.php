<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Services\Chat\ChatRoomSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatRoomSummary
 */
class ChatRoomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->tripId,
            'booking_type' => $this->bookingType,
            'booking_id' => $this->bookingId,
            'other_party' => [
                'id' => $this->otherParty->id,
                'name' => $this->otherParty->name,
                'avatar_url' => $this->otherParty->avatar_url,
            ],
            'recent_message' => $this->recentMessage,
            'last_message_at' => $this->lastMessageAt,
            'unread_count' => $this->unreadCount,
        ];
    }
}
