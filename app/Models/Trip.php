<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\TripFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property 'ride'|'delivery'|'ride_share'|'delivery_share' $type
 * @property CarbonImmutable $requested_at
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $arrived_at
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $cancelled_at
 *
 * Legacy, pre-unification columns -- nullable, no longer in $fillable/casts() since nothing
 * writes them anymore, but still physically present (kept until production's backfill is
 * confirmed complete and a later migration drops them for real). BackfillTripPassengerModel
 * is the one remaining legitimate reader.
 * @property int|null $customer_id
 * @property Point|null $pickup_location
 * @property string|null $pickup_address
 * @property Point|null $dropoff_location
 * @property string|null $dropoff_address
 * @property float|null $distance_km
 * @property int|null $duration_minutes
 * @property float|null $estimated_fare
 * @property float|null $final_fare
 * @property string|null $currency_code
 * @property string|null $payment_method
 * @property string|null $payment_status
 */
class Trip extends BaseModel
{
    /** @use HasFactory<TripFactory> */
    use HasFactory;

    protected $fillable = [
        'trip_number',
        'rider_id',
        'vehicle_id',
        'vehicle_type_id',
        'zone_id',
        'type',
        'status',
        'available_seats',
        'passenger_count',
        'available_cargo_weight_kg',
        'route_polyline',
        'route_distance_km',
        'route_duration_minutes',
        'requested_at',
        'accepted_at',
        'arrived_at',
        'started_at',
        'completed_at',
        'cancelled_at',
        'cancelled_by',
        'cancellation_reason_id',
    ];

    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'accepted_at' => 'datetime',
            'arrived_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'available_seats' => 'integer',
            'passenger_count' => 'integer',
            'available_cargo_weight_kg' => 'decimal:2',
            'route_distance_km' => 'decimal:2',
            'route_duration_minutes' => 'integer',
        ];
    }

    /**
     * The customer on this trip's earliest (first-joined) passenger/delivery -- for a solo
     * ride/delivery, its only one. Trips no longer carry a customer_id of their own; this is
     * the closest equivalent, matching what trips.customer_id always pointed at before the
     * trip_passengers/delivery_details unification. Relies on 'passengers.customer' or
     * 'deliveries.sender' being eager-loaded when available to avoid an N+1 query.
     */
    public function primaryCustomer(): ?User
    {
        if (in_array($this->type, ['ride', 'ride_share'], true)) {
            $passenger = $this->relationLoaded('passengers') ? $this->passengers->first() : $this->passengers()->first();

            return $passenger?->customer;
        }

        $delivery = $this->relationLoaded('deliveries') ? $this->deliveries->first() : $this->deliveries()->first();

        return $delivery?->sender;
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function rider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rider_id');
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
     * @return HasMany<TripStop, $this>
     */
    public function stops(): HasMany
    {
        return $this->hasMany(TripStop::class)->orderBy('sequence');
    }

    /**
     * @return HasMany<DeliveryStop, $this>
     */
    public function deliveryStops(): HasMany
    {
        return $this->hasMany(DeliveryStop::class)->orderBy('sequence');
    }

    /**
     * Ride-share only: the individual passengers matched to this vehicle's shared journey.
     *
     * @return HasMany<TripPassenger, $this>
     */
    public function passengers(): HasMany
    {
        return $this->hasMany(TripPassenger::class)->orderBy('requested_at');
    }

    /**
     * @return HasMany<TripLocation, $this>
     */
    public function locations(): HasMany
    {
        return $this->hasMany(TripLocation::class);
    }

    /**
     * One row per passenger now (via TripFareBreakdown.passenger_id) -- a ride_share trip can
     * have several, so this is no longer a single relation. Use TripPassenger::fareBreakdown()
     * for one specific passenger's own record.
     *
     * @return HasMany<TripFareBreakdown, $this>
     */
    public function fareBreakdowns(): HasMany
    {
        return $this->hasMany(TripFareBreakdown::class);
    }

    /**
     * The individual deliveries pooled onto this vehicle's shared journey -- exactly one
     * for a plain 'delivery' trip, possibly several for 'delivery_share'.
     *
     * @return HasMany<DeliveryDetails, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(DeliveryDetails::class)->orderBy('requested_at');
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
