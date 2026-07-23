<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripStop extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'sequence',
        'location',
        'address',
        'arrived_at',
    ];

    protected function casts(): array
    {
        return [
            'location' => Point::class,
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
}
