<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class TripPassenger extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'customer_id',
        'seats_requested',
        'status',
        'distance_km',
        'duration_minutes',
        'promo_code_id',
        'requested_at',
        'matched_at',
        'picked_up_at',
        'dropped_off_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason_id',
    ];

    protected function casts(): array
    {
        return [
            'seats_requested' => 'integer',
            'distance_km' => 'decimal:2',
            'duration_minutes' => 'integer',
            'requested_at' => 'datetime',
            'matched_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'dropped_off_at' => 'datetime',
            'cancelled_at' => 'datetime',
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
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    /**
     * @return BelongsTo<TripCancellationReason, $this>
     */
    public function cancellationReason(): BelongsTo
    {
        return $this->belongsTo(TripCancellationReason::class, 'cancellation_reason_id');
    }

    /**
     * @return BelongsTo<PromoCode, $this>
     */
    public function promoCode(): BelongsTo
    {
        return $this->belongsTo(PromoCode::class);
    }

    /**
     * @return HasMany<TripStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('sequence');
    }

    /**
     * This passenger's own fare/payment record -- trip_passengers itself is lifecycle/
     * identity only.
     *
     * @return HasOne<TripFareBreakdown, $this>
     */
    public function fareBreakdown(): HasOne
    {
        return $this->hasOne(TripFareBreakdown::class, 'passenger_id');
    }

    /**
     * @return MorphMany<Transaction, $this>
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }
}
