<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class TripCancellationReason extends BaseModel
{
    protected $fillable = [
        'label',
        'applies_to',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Trip, $this>
     */
    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class, 'cancellation_reason_id');
    }
}
