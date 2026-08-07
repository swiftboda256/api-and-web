<?php

namespace App\Services\SupportTicket;

use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatRoom;
use App\Models\ChatRoomParticipant;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

readonly class SupportTicketService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, SupportTicket>
     */
    public function list(User $user, array $filters): LengthAwarePaginator
    {
        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->with(['category', 'chatRoom.messages'])
            ->orderByDesc('created_at')
            ->paginate((int) ($filters['per_page'] ?? 15));
    }

    public function find(User $user, int $ticketId): SupportTicket
    {
        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->where('id', $ticketId)
            ->with([
                'category',
                'trip:id,trip_number,type,status',
                'assignedTo:id,first_name,last_name,other_name',
                'chatRoom.messages' => fn ($query) => $query->where('is_internal_note', false)->orderByDesc('created_at'),
                'chatRoom.messages.sender:id,first_name,last_name,other_name,avatar_url',
                'chatRoom.messages.attachments',
            ])
            ->firstOrFail();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, array $data): SupportTicket
    {
        return DB::transaction(function () use ($user, $data): SupportTicket {
            $ticket = SupportTicket::query()->create([
                'ticket_number' => $this->generateTicketNumber(),
                'user_id' => $user->id,
                'trip_id' => $data['trip_id'] ?? null,
                'category_id' => $data['category_id'],
                'subject' => $data['subject'],
                'status' => 'open',
                'priority' => 'medium',
            ]);

            $chatRoom = ChatRoom::query()->create([
                'type' => 'support',
                'support_ticket_id' => $ticket->id,
                'recent_message' => $data['message'],
                'last_message_at' => now(),
            ]);

            ChatRoomParticipant::query()->create([
                'chat_room_id' => $chatRoom->id,
                'user_id' => $user->id,
            ]);

            $message = ChatMessage::query()->create([
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $user->id,
                'message' => $data['message'],
            ]);

            foreach ($data['attachments'] ?? [] as $url) {
                ChatMessageAttachment::query()->create([
                    'chat_message_id' => $message->id,
                    'type' => $this->guessAttachmentType($url),
                    'url' => $url,
                ]);
            }

            return $ticket->load(['category', 'chatRoom.messages']);
        });
    }

    private function guessAttachmentType(string $url): string
    {
        return match (strtolower((string) pathinfo($url, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
            'mp4', 'mov', 'avi', 'webm' => 'video',
            'mp3', 'wav', 'm4a', 'ogg' => 'audio',
            'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt' => 'document',
            default => 'other',
        };
    }

    private function generateTicketNumber(): string
    {
        do {
            $ticketNumber = 'TKT-'.now()->format('ymd').strtoupper(Str::random(6));
        } while (SupportTicket::query()->where('ticket_number', $ticketNumber)->exists());

        return $ticketNumber;
    }
}
