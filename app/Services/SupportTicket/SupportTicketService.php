<?php

namespace App\Services\SupportTicket;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatRoom;
use App\Models\ChatRoomParticipant;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
        [$ticket, $message] = DB::transaction(function () use ($user, $data): array {
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
                'read_by' => [$user->id],
            ]);

            foreach ($data['attachments'] ?? [] as $url) {
                ChatMessageAttachment::query()->create([
                    'chat_message_id' => $message->id,
                    'type' => $this->guessAttachmentType($url),
                    'url' => $url,
                ]);
            }

            return [$ticket->load(['category', 'chatRoom.messages']), $message];
        });

        event(new ChatMessageSent($message->load(['sender', 'attachments'])));

        return $ticket;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function reply(User $user, int $ticketId, array $data): ChatMessage
    {
        $ticket = $this->ownTicket($user, $ticketId);
        $this->assertTicketOpen($ticket);

        $message = DB::transaction(function () use ($ticket, $user, $data): ChatMessage {
            $chatRoom = $ticket->chatRoom;

            $message = ChatMessage::query()->create([
                'chat_room_id' => $chatRoom->id,
                'sender_id' => $user->id,
                'message' => $data['message'] ?? null,
                'read_by' => [$user->id],
            ]);

            foreach ($data['attachments'] ?? [] as $url) {
                ChatMessageAttachment::query()->create([
                    'chat_message_id' => $message->id,
                    'type' => $this->guessAttachmentType($url),
                    'url' => $url,
                ]);
            }

            $chatRoom->update([
                'recent_message' => $message->message,
                'last_message_at' => now(),
            ]);

            return $message;
        });

        $message->load(['sender', 'attachments']);

        event(new ChatMessageSent($message));

        return $message;
    }

    public function markRead(User $user, int $ticketId): int
    {
        $ticket = $this->ownTicket($user, $ticketId);
        $room = $ticket->chatRoom;

        $touched = 0;

        $room->messages()
            ->where('sender_id', '!=', $user->id)
            ->where('is_internal_note', false)
            ->get()
            ->each(function (ChatMessage $message) use ($user, &$touched): void {
                if (in_array($user->id, $message->read_by, true)) {
                    return;
                }

                $message->update(['read_by' => [...$message->read_by, $user->id]]);
                $touched++;
            });

        return $touched;
    }

    private function ownTicket(User $user, int $ticketId): SupportTicket
    {
        return SupportTicket::query()
            ->where('user_id', $user->id)
            ->where('id', $ticketId)
            ->with('chatRoom')
            ->firstOrFail();
    }

    /**
     * Mirrors the trip-chat closing rule: write-enabled until the ticket is resolved/closed,
     * history stays readable after.
     */
    private function assertTicketOpen(SupportTicket $ticket): void
    {
        if (in_array($ticket->status, ['resolved', 'closed'], true)) {
            throw ValidationException::withMessages([
                'status' => 'This ticket is closed. Open a new ticket if you need further help.',
            ]);
        }
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
