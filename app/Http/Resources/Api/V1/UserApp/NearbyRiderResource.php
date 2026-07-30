<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\RiderProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin RiderProfile
 */
class NearbyRiderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'rider_id' => $this->user_id,
            'name' => $this->user->first_name,
            'rating_avg' => $this->user->rating_avg,
            'rating_count' => $this->user->rating_count,
            'vehicle_type' => $this->vehicle?->vehicleType?->name,
            'vehicle_color' => $this->vehicle?->color,
            'plate_number' => $this->vehicle?->plate_number,
            'latitude' => $this->current_location?->getLatitude(),
            'longitude' => $this->current_location?->getLongitude(),
            'distance_km' => round(((float) $this->distance_meters) / 1000, 2),
        ];
    }
}
