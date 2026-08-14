<?php

namespace App\Http\Resources\Api\V1\RiderApp;

use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Zone
 */
class ZoneResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'city' => $this->city,
            'country' => $this->country,
            'currency_code' => $this->currency_code,
            'timezone' => $this->timezone,
        ];
    }
}
