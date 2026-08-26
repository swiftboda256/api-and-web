<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\PromoCode;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PromoCode
 */
class PromoCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'code' => $this->code,
            'description' => $this->description,
            'discount_type' => $this->discount_type,
            'discount_value' => $this->discount_value,
            'max_discount_amount' => $this->max_discount_amount,
            'min_trip_amount' => $this->min_trip_amount,
            'valid_until' => $this->valid_until,
        ];
    }
}
