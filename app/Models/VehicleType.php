<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleType extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'capacity',
        'icon_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Vehicle, $this>
     */
    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
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
}
