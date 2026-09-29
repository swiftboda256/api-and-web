<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Point;
use Database\Factories\RiderProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property float|null $distance_meters Only populated by RiderSearchService::nearby(), via a raw ST::distanceSphere select.
 */
class RiderProfile extends BaseModel
{
    /** @use HasFactory<RiderProfileFactory> */
    use HasFactory;

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
     * Riders whose wallet balance hasn't dropped below their home zone's
     * minimum_negative_balance (from cash-trip commission deductions). A rider with no
     * wallet counts as a 0 balance; a rider with no home zone gets a floor of 0.
     *
     * @param  Builder<RiderProfile>  $query
     */
    #[Scope]
    protected function withinWalletLimit(Builder $query): void
    {
        $query->whereRaw(
            'COALESCE((SELECT wallets.balance FROM wallets WHERE wallets.user_id = rider_profiles.user_id AND wallets.deleted_at IS NULL), 0)
                >= COALESCE((SELECT zones.minimum_negative_balance FROM zones WHERE zones.id = rider_profiles.home_zone_id), 0)',
        );
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
