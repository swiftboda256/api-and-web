<?php

namespace App\Http\Resources\Api\V1\RiderApp;

use App\Models\WithdrawCharge;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin WithdrawCharge
 */
class WithdrawChargeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'channel' => $this->channel,
            'min_amount' => $this->min_amount,
            'max_amount' => $this->max_amount,
            'base_charge' => $this->base_charge,
            'mtn_charge' => $this->mtn_charge,
            'airtel_charge' => $this->airtel_charge,
        ];
    }
}
