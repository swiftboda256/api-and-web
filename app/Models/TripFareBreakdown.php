<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripFareBreakdown extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'base_fare',
        'distance_fare',
        'time_fare',
        'surge_multiplier',
        'surge_amount',
        'discount_amount',
        'cancellation_fee',
        'commission_rate',
        'commission_amount',
        'rider_earning',
        'total',
        'currency_code',
    ];

    protected function casts(): array
    {
        return [
            'base_fare' => 'decimal:2',
            'distance_fare' => 'decimal:2',
            'time_fare' => 'decimal:2',
            'surge_multiplier' => 'decimal:2',
            'surge_amount' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'cancellation_fee' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'commission_amount' => 'decimal:2',
            'rider_earning' => 'decimal:2',
            'total' => 'decimal:2',
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
