<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserDevice extends BaseModel
{
    protected $fillable = [
        'user_id',
        'device_type',
        'device_id',
        'fcm_token',
        'app_version',
        'ip_address',
        'last_seen_at',
        'active',
    ];

    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'active' => 'boolean',
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
