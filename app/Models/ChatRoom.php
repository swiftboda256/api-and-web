<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class ChatRoom extends BaseModel
{
    protected $fillable = [
        'recent_message',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'last_message_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    /**
     * @return HasMany<ChatRoomParticipant, $this>
     */
    public function participants(): HasMany
    {
        return $this->hasMany(ChatRoomParticipant::class);
    }
}
