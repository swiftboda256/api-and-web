<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $status
 * @property string|null $reason
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable $scheduled_for
 * @property CarbonImmutable|null $processing_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 * @property string|null $failure_reason
 */
class UserDeleteRequest extends BaseModel
{
    protected $fillable = [
        'user_id',
        'status',
        'reason',
        'requested_at',
        'scheduled_for',
        'processing_at',
        'completed_at',
        'cancelled_at',
        'failure_reason',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'scheduled_for' => 'datetime',
            'processing_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
