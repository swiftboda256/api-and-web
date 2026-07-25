<?php

namespace App\Http\Resources\Api\V1\UserApp;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TripEstimateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var array<string, mixed> $estimate */
        $estimate = $this->resource;

        return [
            'distance_km' => $estimate['distance_km'],
            'duration_minutes' => $estimate['duration_minutes'],
            'currency_code' => $estimate['currency_code'],
            'base_fare' => $estimate['base_fare'],
            'distance_fare' => $estimate['distance_fare'],
            'time_fare' => $estimate['time_fare'],
            'surge_multiplier' => $estimate['surge_multiplier'],
            'surge_amount' => $estimate['surge_amount'],
            'discount_amount' => $estimate['discount_amount'],
            'promo_code' => $estimate['promo_code'],
            'estimated_fare' => $estimate['estimated_fare'],
        ];
    }
}
