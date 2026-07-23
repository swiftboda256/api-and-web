<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripLocation extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'rider_profile_id',
        'location',
        'heading',
        'speed',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'location' => Point::class,
            'heading' => 'decimal:2',
            'speed' => 'decimal:2',
            'recorded_at' => 'datetime',
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
     * @return BelongsTo<RiderProfile, $this>
     */
    public function riderProfile(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }
}
