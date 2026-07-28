<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Vehicle
 */
class VehicleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle_type' => $this->whenLoaded('vehicleType', fn () => [
                'id' => $this->vehicleType->id,
                'name' => $this->vehicleType->name,
                'code' => $this->vehicleType->code,
            ]),
            'make' => $this->make,
            'year' => $this->year,
            'color' => $this->color,
            'plate_number' => $this->plate_number,
            'registration_number' => $this->registration_number,
            'insurance_expiry_at' => $this->insurance_expiry_at,
            'status' => $this->status,
        ];
    }
}
