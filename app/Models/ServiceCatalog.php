<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ServiceCatalog extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'image_url',
        'service_type',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<VehicleType, $this>
     */
    public function vehicleTypes(): BelongsToMany
    {
        return $this->belongsToMany(VehicleType::class, 'service_catalog_vehicle_types')->withTimestamps();
    }
}
