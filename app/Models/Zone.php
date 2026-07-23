<?php

namespace App\Models;

use Clickbar\Magellan\Data\Geometries\Polygon;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Zone extends BaseModel
{
    protected $fillable = [
        'name',
        'city',
        'country',
        'boundary',
        'currency_code',
        'timezone',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'boundary' => Polygon::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PricingRule, $this>
     */
    public function pricingRules(): HasMany
    {
        return $this->hasMany(PricingRule::class);
    }

    /**
     * @return HasMany<SurgePricingSchedule, $this>
     */
    public function surgePricingSchedules(): HasMany
    {
        return $this->hasMany(SurgePricingSchedule::class);
    }

    /**
     * @return HasMany<RiderProfile, $this>
     */
    public function riderProfiles(): HasMany
    {
        return $this->hasMany(RiderProfile::class, 'home_zone_id');
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }
}
