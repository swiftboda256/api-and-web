<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Address;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Address
 */
class AddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'latitude' => $this->location->getLatitude(),
            'longitude' => $this->location->getLongitude(),
            'formatted_address' => $this->formatted_address,
            'place_id' => $this->place_id,
            'is_default' => $this->is_default,
            'type' => $this->category,
            'created_at' => $this->created_at,
        ];
    }
}
