<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property 'requested'|'pending_approval'|'matched'|'arrived_pickup'|'picked_up'|'arrived_dropoff'|'dropped_off'|'cancelled' $status
 * @property CarbonImmutable|null $picked_up_at
 * @property CarbonImmutable|null $request_expires_at
 * @property list<int>|null $declined_trip_ids
 */
class TripPassenger extends BaseModel
{
    protected $fillable = [
        'trip_id',
        'customer_id',
        'seats_requested',
        'status',
        'distance_km',
        'duration_minutes',
        'detour_minutes',
        'detour_km',
        'promo_code_id',
        'requested_at',
        'request_expires_at',
        'declined_trip_ids',
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
            'detour_minutes' => 'integer',
            'detour_km' => 'decimal:2',
            'requested_at' => 'datetime',
            'request_expires_at' => 'datetime',
            'declined_trip_ids' => 'array',
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
