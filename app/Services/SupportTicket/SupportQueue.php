<?php

namespace App\Services\SupportTicket;

use App\Models\SupportTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which tickets sit in which tab of the admin Support Tickets page (also used by its nav badge and
 * the dashboard). "Active" = open or in progress.
 *
 * - needs_reply: active, and the last public message is from the requester (waiting on support)
 * - mine:        active and assigned to the given agent
 * - unassigned:  active with nobody assigned
 * - open:        all active tickets
 * - resolved / closed / all
 */
class SupportQueue
{
    public const array TABS = ['needs_reply', 'mine', 'unassigned', 'open', 'resolved', 'closed', 'all'];

    public const array ACTIVE_STATUSES = ['open', 'in_progress'];

    /**
     * @return Builder<SupportTicket>
     */
    public static function query(string $tab = 'all', ?int $agentId = null): Builder
    {
        $query = SupportTicket::query();

        return match ($tab) {
            'needs_reply' => self::active($query)->whereRaw(self::lastPublicSenderSql().' = support_tickets.user_id'),
            'mine' => self::active($query)->where('assigned_to', $agentId ?? 0),
            'unassigned' => self::active($query)->whereNull('assigned_to'),
            'open' => self::active($query),
            'resolved' => $query->where('status', 'resolved'),
            'closed' => $query->where('status', 'closed'),
            default => $query,
        };
    }

    public static function needsReplyCount(): int
    {
        return self::query('needs_reply')->count();
    }

    /**
     * Adds `last_public_message_at` and `awaiting_support` (last public message is from the requester).
     *
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    public static function withActivity(Builder $query): Builder
    {
        return $query->addSelect([
            'last_public_message_at' => DB::table('chat_messages')
                ->join('chat_rooms', 'chat_rooms.id', '=', 'chat_messages.chat_room_id')
                ->whereColumn('chat_rooms.support_ticket_id', 'support_tickets.id')
                ->where('chat_messages.is_internal_note', false)
                ->whereNull('chat_messages.deleted_at')
                ->selectRaw('MAX(chat_messages.created_at)'),
        ])->selectRaw('('.self::lastPublicSenderSql().' = support_tickets.user_id) AS awaiting_support');
    }

    /**
     * @param  Builder<SupportTicket>  $query
     * @return Builder<SupportTicket>
     */
    private static function active(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    /**
     * Sender of the ticket's latest non-internal message.
     *
     * @return literal-string
     */
    private static function lastPublicSenderSql(): string
    {
        return '(SELECT chat_messages.sender_id FROM chat_messages
            JOIN chat_rooms ON chat_rooms.id = chat_messages.chat_room_id
            WHERE chat_rooms.support_ticket_id = support_tickets.id
              AND chat_messages.is_internal_note = false
              AND chat_messages.deleted_at IS NULL
            ORDER BY chat_messages.created_at DESC, chat_messages.id DESC
            LIMIT 1)';
    }
}
