<?php

namespace App\Models;

/**
 * @property int $id
 * @property string|null $channel
 * @property float $min_amount
 * @property float|null $max_amount
 * @property float $base_charge
 * @property float $mtn_charge
 * @property float $airtel_charge
 * @property bool $is_active
 */
class WithdrawCharge extends BaseModel
{
    protected $fillable = [
        'channel',
        'min_amount',
        'max_amount',
        'base_charge',
        'mtn_charge',
        'airtel_charge',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'min_amount' => 'decimal:2',
            'max_amount' => 'decimal:2',
            'base_charge' => 'decimal:2',
            'mtn_charge' => 'decimal:2',
            'airtel_charge' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
