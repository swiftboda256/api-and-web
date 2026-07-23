<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PricingRule extends BaseModel
{
    protected $fillable = [
        'zone_id',
        'vehicle_type_id',
        'base_fare',
        'per_km_rate',
        'per_minute_rate',
        'minimum_fare',
        'cancellation_fee',
        'commission_rate',
        'surge_multiplier',
        'currency_code',
        'effective_from',
        'effective_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'base_fare' => 'decimal:2',
            'per_km_rate' => 'decimal:2',
            'per_minute_rate' => 'decimal:2',
            'minimum_fare' => 'decimal:2',
            'cancellation_fee' => 'decimal:2',
            'commission_rate' => 'decimal:2',
            'surge_multiplier' => 'decimal:2',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
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
