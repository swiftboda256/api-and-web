<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryStop extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'delivery_details_id',
        'stop_type',
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

    /**
     * @return BelongsTo<DeliveryDetails, $this>
     */
    public function deliveryDetails(): BelongsTo
    {
        return $this->belongsTo(DeliveryDetails::class);
    }
}
