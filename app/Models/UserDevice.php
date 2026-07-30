<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $device_type
 * @property string|null $device_id
 * @property string|null $fcm_token
 * @property string|null $app_version
 * @property string|null $ip_address
 * @property bool $active
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
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
