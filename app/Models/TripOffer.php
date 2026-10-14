<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A job offer sent to one rider while the trip searches -- see TripDispatchService.
 *
 * @property 'pending'|'accepted'|'expired'|'withdrawn' $status
 * @property CarbonImmutable $offered_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $closed_at
 */
class TripOffer extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'rider_id',
        'status',
        'offered_at',
        'expires_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'offered_at' => 'datetime',
            'expires_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
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
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
    }
}
