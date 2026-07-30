<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property float|null $distance_meters Only populated by RiderSearchService::nearby(), via a raw ST::distanceSphere select.
 */
class RiderProfile extends BaseModel
{
    protected $fillable = [
        'user_id',
        'rider_ref',
        'national_id_number',
        'license_number',
        'license_expiry_at',
        'date_of_birth',
        'gender',
        'kyc_status',
        'kyc_rejection_reason',
        'approved_at',
        'approved_by',
        'availability_status',
        'current_location',
        'last_location_at',
        'home_zone_id',
        'total_trips',
        'total_earnings',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry_at' => 'date',
            'date_of_birth' => 'date',
            'approved_at' => 'datetime',
            'current_location' => Point::class,
            'last_location_at' => 'datetime',
            'total_trips' => 'integer',
            'total_earnings' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<Zone, $this>
     */
    public function homeZone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'home_zone_id');
    }

    /**
     * @return HasOne<Vehicle, $this>
     */
    public function vehicle(): HasOne
    {
        return $this->hasOne(Vehicle::class);
    }

    /**
     * @return MorphMany<Document, $this>
     */
    public function documents(): MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
