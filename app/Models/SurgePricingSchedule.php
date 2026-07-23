<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SurgePricingSchedule extends BaseModel
{
    protected $fillable = [
        'zone_id',
        'vehicle_type_id',
        'day_of_week',
        'start_time',
        'end_time',
        'multiplier',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'multiplier' => 'decimal:2',
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
