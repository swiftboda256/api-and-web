<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property 'pickup'|'dropoff'|null $stop_type
 * @property Point|null $location
 * @property CarbonImmutable|null $arrived_at
 */
class TripStop extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'trip_passenger_id',
        'stop_type',
        'seats_delta',
        'sequence',
        'location',
        'address',
        'arrived_at',
    ];

    protected function casts(): array
    {
        return [
            'location' => Point::class,
            'seats_delta' => 'integer',
            'arrived_at' => 'datetime',
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
     * Ride-share only: which passenger this pickup/dropoff stop belongs to.
     *
     * @return BelongsTo<TripPassenger, $this>
     */
    public function tripPassenger(): BelongsTo
    {
        return $this->belongsTo(TripPassenger::class);
    }
}
