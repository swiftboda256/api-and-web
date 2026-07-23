<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatRoomParticipant extends BaseModel
{
    protected $fillable = [
        'chat_room_id',
        'user_id',
    ];

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
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
