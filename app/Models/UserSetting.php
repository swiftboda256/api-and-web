<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserSetting extends BaseModel
{
    protected $fillable = [
        'user_id',
        'language',
        'notifications_enabled',
        'notification_channels',
    ];

    protected function casts(): array
    {
        return [
            'notifications_enabled' => 'boolean',
            'notification_channels' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
