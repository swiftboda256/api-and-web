<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryDetails extends BaseModel
{
    protected $table = 'delivery_details';

    protected $fillable = [
        'trip_id',
        'recipient_name',
        'recipient_phone',
        'package_description',
        'package_size',
        'package_weight_kg',
        'requires_signature',
        'proof_of_delivery_photo',
        'delivered_to_name',
        'delivery_notes',
    ];

    protected function casts(): array
    {
        return [
            'package_weight_kg' => 'decimal:2',
            'requires_signature' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Trip, $this>
     */
    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
