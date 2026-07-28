<?php

namespace App\Http\Resources\Api\V1\RiderApp;

use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleType
 */
class VehicleTypeResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'capacity' => $this->capacity,
            'icon_url' => $this->icon_url,
        ];
    }
}
