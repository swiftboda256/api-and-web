<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\ServiceCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceCatalog
 */
class ServiceCatalogResource extends JsonResource
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
            'image_url' => $this->image_url,
            'service_type' => $this->service_type,
            'is_active' => $this->is_active,
            'vehicle_types' => VehicleTypeResource::collection($this->whenLoaded('vehicleTypes')),
        ];
    }
}
