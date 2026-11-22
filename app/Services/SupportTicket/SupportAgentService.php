<?php

namespace App\Services\SupportTicket;

use App\Events\ChatMessageSent;
use App\Models\ChatMessage;
use App\Models\ChatMessageAttachment;
use App\Models\ChatRoom;
use App\Models\ChatRoomParticipant;
use App\Models\SupportTicket;
use App\Models\User;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The support agent's side of a ticket (admin panel). The customer/rider side is SupportTicketService.
 *
 * Replies go into the ticket's chat room like customer messages (same ChatMessageSent broadcast, so an
 * open ticket screen in the app updates live) and push `support_ticket_reply` to the ticket owner.
 * Internal notes are stored in the same room with is_internal_note = true: never broadcast, never pushed,
 * and filtered out of every app endpoint.
 */
readonly class SupportAgentService
{
    public const array STATUSES = ['open', 'in_progress', 'resolved', 'closed'];

    public const array PRIORITIES = ['low', 'medium', 'high', 'urgent'];

    public function __construct(
        private FcmGateway $push,
    ) {}

    /**
     * @param  list<array{url: string, mime_type: string|null, size: int|null}>  $attachments
     * @param  string|null  $statusAfter  e.g. 'resolved' for "reply & resolve"
     */
    public function reply(SupportTicket $ticket, User $agent, ?string $message, array $attachments = [], ?string $statusAfter = null): ChatMessage
    {
        $chatMessage = DB::transaction(function () use ($ticket, $agent, $message, $attachments, $statusAfter): ChatMessage {
            $room = $this->room($ticket);
            $chatMessage = $this->storeMessage($room, $agent, $message, $attachments, internal: false);

            $room->update([
                'recent_message' => $chatMessage->message ?? 'Sent an attachment',
                'last_message_at' => now(),
            ]);

            // Picking up a ticket by answering it: claim it if nobody has, and move it out of "open".
            $ticket->assigned_to ??= $agent->id;

            if ($statusAfter !== null) {
                $this->applyStatus($ticket, $statusAfter);
            } elseif ($ticket->status === 'open') {
                $ticket->status = 'in_progress';
            }

            $ticket->save();

            return $chatMessage;
        });

        $chatMessage->load(['sender', 'attachments']);

        event(new ChatMessageSent($chatMessage));

        $this->push->sendToUser($ticket->user, new PushMessage(
            type: 'support_ticket_reply',
            id: $ticket->id,
            title: "Support · {$ticket->subject}",
            body: $chatMessage->message ?? 'Sent an attachment',
            data: [
                'support_ticket_id' => $ticket->id,
                'chat_room_id' => $chatMessage->chat_room_id,
                'ticket_number' => $ticket->ticket_number,
            ],
            interruptionLevel: 'active',
        ));

        return $chatMessage;
    }

    /**
     * @param  list<array{url: string, mime_type: string|null, size: int|null}>  $attachments
     */
    public function addInternalNote(SupportTicket $ticket, User $agent, ?string $note, array $attachments = []): ChatMessage
    {
        return DB::transaction(fn (): ChatMessage => $this->storeMessage($this->room($ticket), $agent, $note, $attachments, internal: true))
            ->load(['sender', 'attachments']);
    }

    public function changeStatus(SupportTicket $ticket, string $status): SupportTicket
    {
        $previous = $ticket->status;

        $this->applyStatus($ticket, $status);
        $ticket->save();

        if ($status === 'resolved' && $previous !== 'resolved') {
            $this->push->sendToUser($ticket->user, new PushMessage(
                type: 'support_ticket_resolved',
                id: $ticket->id,
                title: 'Support ticket resolved',
                body: "Your ticket \"{$ticket->subject}\" has been marked as resolved.",
                data: ['support_ticket_id' => $ticket->id, 'ticket_number' => $ticket->ticket_number],
            ));
        }

        return $ticket;
    }

    public function changePriority(SupportTicket $ticket, string $priority): SupportTicket
    {
        $ticket->update(['priority' => $priority]);

        return $ticket;
    }

    public function changeCategory(SupportTicket $ticket, int $categoryId): SupportTicket
    {
        $ticket->update(['category_id' => $categoryId]);

        return $ticket;
    }

    public function assign(SupportTicket $ticket, ?User $agent): SupportTicket
    {
        $ticket->update(['assigned_to' => $agent?->id]);

        return $ticket;
    }

    /**
     * The agent has seen the conversation: add them to read_by of the requester's messages.
     */
    public function markRead(SupportTicket $ticket, User $agent): void
    {
        $ticket->chatRoom?->messages()
            ->where('sender_id', '!=', $agent->id)
            ->where('is_internal_note', false)
            ->get()
            ->each(function (ChatMessage $message) use ($agent): void {
                if (! in_array($agent->id, $message->read_by, true)) {
                    $message->update(['read_by' => [...$message->read_by, $agent->id]]);
                }
            });
    }

    public static function attachmentType(?string $mimeType, string $url): string
    {
        $mimeType = (string) $mimeType;

        return match (true) {
            Str::startsWith($mimeType, 'image/') => 'image',
            Str::startsWith($mimeType, 'video/') => 'video',
            Str::startsWith($mimeType, 'audio/') => 'audio',
            $mimeType === 'application/pdf', in_array(strtolower((string) pathinfo($url, PATHINFO_EXTENSION)), ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt'], true) => 'document',
            default => 'other',
        };
    }

    private function applyStatus(SupportTicket $ticket, string $status): void
    {
        $ticket->status = $status;
        $ticket->resolved_at = in_array($status, ['resolved', 'closed'], true) ? ($ticket->resolved_at ?? now()) : null;
    }

    /**
     * The ticket's room (created with the ticket; recreated only for legacy tickets without one).
     */
    private function room(SupportTicket $ticket): ChatRoom
    {
        $room = $ticket->chatRoom ?? ChatRoom::query()->create([
            'type' => 'support',
            'support_ticket_id' => $ticket->id,
        ]);

        $ticket->setRelation('chatRoom', $room);

        ChatRoomParticipant::query()->firstOrCreate(['chat_room_id' => $room->id, 'user_id' => $ticket->user_id]);

        return $room;
    }

    /**
     * @param  list<array{url: string, mime_type: string|null, size: int|null}>  $attachments
     */
    private function storeMessage(ChatRoom $room, User $agent, ?string $message, array $attachments, bool $internal): ChatMessage
    {
        // The agent joins the room so the participant-based channel auth also lets them subscribe.
        ChatRoomParticipant::query()->firstOrCreate(['chat_room_id' => $room->id, 'user_id' => $agent->id]);

        $chatMessage = ChatMessage::query()->create([
            'chat_room_id' => $room->id,
            'sender_id' => $agent->id,
            'message' => $message !== null && trim($message) !== '' ? trim($message) : null,
            'is_internal_note' => $internal,
            'read_by' => [$agent->id],
        ]);

        foreach ($attachments as $attachment) {
            ChatMessageAttachment::query()->create([
                'chat_message_id' => $chatMessage->id,
                'type' => self::attachmentType($attachment['mime_type'], $attachment['url']),
                'url' => $attachment['url'],
                'mime_type' => $attachment['mime_type'],
                'size' => $attachment['size'],
            ]);
        }

        return $chatMessage;
    }
}
