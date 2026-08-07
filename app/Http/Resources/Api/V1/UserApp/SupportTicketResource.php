<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\SupportTicket;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin SupportTicket
 */
class SupportTicketResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ticket_number' => $this->ticket_number,
            'trip_id' => $this->trip_id,
            'category' => $this->whenLoaded('category', fn () => [
                'id' => $this->category->id,
                'name' => $this->category->name,
                'code' => $this->category->code,
            ]),
            'subject' => $this->subject,
            'status' => $this->status,
            'priority' => $this->priority,
            'resolved_at' => $this->resolved_at,
            'trip' => $this->whenLoaded('trip', fn () => $this->trip ? [
                'id' => $this->trip->id,
                'trip_number' => $this->trip->trip_number,
                'type' => $this->trip->type,
                'status' => $this->trip->status,
            ] : null),
            'assigned_to' => $this->whenLoaded('assignedTo', fn () => $this->assignedTo ? [
                'id' => $this->assignedTo->id,
                'name' => $this->assignedTo->name,
            ] : null),
            'chat_room' => $this->whenLoaded('chatRoom', fn () => [
                'id' => $this->chatRoom->id,
                'messages' => ChatMessageResource::collection($this->chatRoom->messages),
            ]),
            'created_at' => $this->created_at,
        ];
    }
}
