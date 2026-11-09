<?php

namespace App\Services\Chat;

use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * One trip-chat room's state from one side's point of view, whether or not the underlying
 * ChatRoom row has been created yet (it's only created on the first message -- see
 * ChatService::findOrCreateRoom()).
 */
final readonly class ChatRoomSummary
{
    public function __construct(
        public ?int $id,
        public int $tripId,
        public string $bookingType,
        public int $bookingId,
        public User $otherParty,
        public ?string $recentMessage,
        public ?CarbonImmutable $lastMessageAt,
        public int $unreadCount,
    ) {}
}
