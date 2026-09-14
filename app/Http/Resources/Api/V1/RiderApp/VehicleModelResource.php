<?php

namespace App\Http\Resources\Api\V1\RiderApp;

use App\Models\VehicleModel;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleModel
 */
class VehicleModelResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_type_id' => $this->vehicle_type_id,
            'make' => $this->make,
            'name' => $this->name,
        ];
    }
}
