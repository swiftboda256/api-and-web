<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class PromoCode extends BaseModel
{
    protected $fillable = [
        'code',
        'description',
        'discount_type',
        'discount_value',
        'max_discount_amount',
        'min_trip_amount',
        'usage_limit_total',
        'usage_limit_per_user',
        'applicable_vehicle_types',
        'applicable_zone_ids',
        'valid_from',
        'valid_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'min_trip_amount' => 'decimal:2',
            'applicable_vehicle_types' => 'array',
            'applicable_zone_ids' => 'array',
            'valid_from' => 'datetime',
            'valid_until' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /**
     * @return HasMany<PromoCodeRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }
}
