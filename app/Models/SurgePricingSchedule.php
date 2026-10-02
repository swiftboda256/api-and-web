<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurgePricingSchedule extends BaseModel
{
    /**
     * multiplier is no longer used in fare calculation or set from the admin form,
     * but the column is NOT NULL, so new rows default to 1.
     */
    protected $attributes = [
        'multiplier' => 1,
    ];

    protected $fillable = [
        'zone_id',
        'vehicle_type_id',
        'day_of_week',
        'start_time',
        'end_time',
        'multiplier',
        'fixed_amount',
        'per_km_rate',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
            'fixed_amount' => 'decimal:2',
            'per_km_rate' => 'decimal:2',
            'is_active' => 'boolean',
        ];
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
}
