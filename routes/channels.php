<?php

use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('chat-room.{chatRoom}', function (User $user, ChatRoom $chatRoom): bool {
    return $chatRoom->participants()->where('user_id', $user->id)->exists();
});
