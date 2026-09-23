<?php

namespace App\Models;

use Database\Factories\VehicleTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleType extends BaseModel
{
    /** @use HasFactory<VehicleTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'capacity',
        'max_cargo_weight_kg',
        'icon_url',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_cargo_weight_kg' => 'decimal:2',
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
     * @return HasMany<VehicleModel, $this>
     */
    public function vehicleModels(): HasMany
    {
        return $this->hasMany(VehicleModel::class);
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
     * @return BelongsToMany<ServiceCatalog, $this>
     */
    public function serviceCatalogs(): BelongsToMany
    {
        return $this->belongsToMany(ServiceCatalog::class, 'service_catalog_vehicle_types')->withTimestamps();
    }
}
