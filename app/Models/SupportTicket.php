<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $ticket_number
 * @property int $user_id
 * @property int|null $trip_id
 * @property int $category_id
 * @property string $subject
 * @property string $status
 * @property string $priority
 * @property int|null $assigned_to
 * @property CarbonImmutable|null $resolved_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class SupportTicket extends BaseModel
{
    protected $fillable = [
        'ticket_number',
        'user_id',
        'trip_id',
        'category_id',
        'subject',
        'status',
        'priority',
        'assigned_to',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'resolved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<SupportCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(SupportCategory::class);
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasOne<ChatRoom, $this>
     */
    public function chatRoom(): HasOne
    {
        return $this->hasOne(ChatRoom::class);
    }
}
