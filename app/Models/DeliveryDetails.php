<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\DeliveryDetailsFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class DeliveryDetails extends BaseModel
{
    /** @use HasFactory<DeliveryDetailsFactory> */
    use HasFactory;

    protected $table = 'delivery_details';

    protected $fillable = [
        'trip_id',
        'sender_id',
        'status',
        'pickup_location',
        'pickup_address',
        'dropoff_location',
        'dropoff_address',
        'distance_km',
        'duration_minutes',
        'base_fare_amount',
        'discount_percentage',
        'promo_code_id',
        'discount_amount',
        'estimated_fare',
        'final_fare',
        'currency_code',
        'payment_method',
        'payment_status',
        'requested_at',
        'matched_at',
        'picked_up_at',
        'dropped_off_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason_id',
        'recipient_name',
        'recipient_phone',
        'package_description',
        'package_size',
        'package_weight_kg',
        'requires_signature',
        'proof_of_delivery_photo',
        'delivered_to_name',
        'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'pickup_location' => Point::class,
            'dropoff_location' => Point::class,
            'distance_km' => 'decimal:2',
            'duration_minutes' => 'integer',
            'base_fare_amount' => 'decimal:2',
            'discount_percentage' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'estimated_fare' => 'decimal:2',
            'final_fare' => 'decimal:2',
            'requested_at' => 'datetime',
            'matched_at' => 'datetime',
            'picked_up_at' => 'datetime',
            'dropped_off_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'package_weight_kg' => 'decimal:2',
            'requires_signature' => 'boolean',
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
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
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
     * @return HasMany<DeliveryStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('sequence');
    }

    /**
     * @return MorphMany<Transaction, $this>
     */
    public function transactions(): MorphMany
    {
        return $this->morphMany(Transaction::class, 'reference');
    }
}
