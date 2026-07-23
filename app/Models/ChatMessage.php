<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatMessage extends BaseModel
{
    protected $fillable = [
        'chat_room_id',
        'sender_id',
        'message',
        'read_by',
    ];

    protected function casts(): array
    {
        return [
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
