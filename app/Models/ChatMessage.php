<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $chat_room_id
 * @property int $sender_id
 * @property string|null $message
 * @property bool $is_internal_note
 * @property array<int, mixed> $read_by
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class ChatMessage extends BaseModel
{
    protected $fillable = [
        'chat_room_id',
        'sender_id',
        'message',
        'is_internal_note',
        'read_by',
    ];

    protected function casts(): array
    {
        return [
            'is_internal_note' => 'boolean',
            'read_by' => 'array',
        ];
    }

    /**
     * @return BelongsTo<ChatRoom, $this>
     */
    public function chatRoom(): BelongsTo
    {
        return $this->belongsTo(ChatRoom::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * @return HasMany<ChatMessageAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ChatMessageAttachment::class);
    }
}
