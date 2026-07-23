<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Vehicle extends BaseModel
{
    protected $fillable = [
        'rider_profile_id',
        'vehicle_type_id',
        'make',
        'year',
        'color',
        'plate_number',
        'registration_number',
        'insurance_expiry_at',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'insurance_expiry_at' => 'date',
        ];
    }

    /**
     * @return BelongsTo<RiderProfile, $this>
     */
    public function riderProfile(): BelongsTo
    {
        return $this->belongsTo(RiderProfile::class);
    }

    /**
     * @return BelongsTo<VehicleType, $this>
     */
    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
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
