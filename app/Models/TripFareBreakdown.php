<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripFareBreakdown extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'passenger_id',
        'customer_id',
        'base_fare',
        'distance_fare',
        'time_fare',
        'surge_multiplier',
        'surge_amount',
        'discount_percentage',
        'discount_amount',
        'cancellation_fee',
        'commission_rate',
        'commission_amount',
        'rider_earning',
        'currency_code',
        'estimated_fare',
        'estimated_fare_before_rounding',
        'final_fare',
        'final_fare_before_rounding',
        'payment_method',
        'payment_status',
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
            'estimated_fare' => 'decimal:2',
            'estimated_fare_before_rounding' => 'decimal:2',
            'final_fare' => 'decimal:2',
            'final_fare_before_rounding' => 'decimal:2',
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

    /**
     * @return BelongsTo<TripPassenger, $this>
     */
    public function passenger(): BelongsTo
    {
        return $this->belongsTo(TripPassenger::class, 'passenger_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}
