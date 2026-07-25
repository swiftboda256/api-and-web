<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Trip extends BaseModel
{
    protected $fillable = [
        'trip_number',
        'customer_id',
        'rider_profile_id',
        'vehicle_id',
        'vehicle_type_id',
        'zone_id',
        'type',
        'status',
        'pickup_location',
        'pickup_address',
        'dropoff_location',
        'dropoff_address',
        'requested_at',
        'accepted_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason_id',
        'distance_km',
        'duration_minutes',
        'estimated_fare',
        'final_fare',
        'currency_code',
        'promo_code_id',
        'discount_amount',
        'payment_method',
        'payment_status',
    ];

    protected function casts(): array
    {
        return [
            'pickup_location' => Point::class,
            'dropoff_location' => Point::class,
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'distance_km' => 'decimal:2',
            'duration_minutes' => 'integer',
            'estimated_fare' => 'decimal:2',
            'final_fare' => 'decimal:2',
            'discount_amount' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsTo<RiderProfile, $this>
     */
    public function riderProfile(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }

    /**
     * @return BelongsTo<Vehicle, $this>
     */
    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class);
    }

    /**
     * @return BelongsTo<VehicleType, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
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
     * @return HasMany<TripLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(TripLocation::class);
    }

    /**
     * @return HasOne<TripFareBreakdown, $this>
     */
    public function fareBreakdown(): HasOne
    {
        return $this->hasOne(TripFareBreakdown::class);
    }

    /**
     * @return HasOne<DeliveryDetails, $this>
     */
    public function deliveryDetails(): HasOne
    {
        return $this->hasOne(DeliveryDetails::class);
    }

    /**
     * @return HasMany<Rating, $this>
     */
    public function ratings(): HasMany
    {
        return $this->hasMany(Rating::class);
    }

    /**
     * @return HasMany<PromoCodeRedemption, $this>
     */
    public function promoCodeRedemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    /**
     * @return HasMany<SupportTicket, $this>
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * @return MorphMany<Transaction, $this>
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }
}
