<?php

namespace App\Http\Resources\Api\V1\RiderApp;

use App\Models\TripLocation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TripLocation
 */
class RideLocationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'location' => [
                'latitude' => $this->location->getLatitude(),
                'longitude' => $this->location->getLongitude(),
            ],
            'heading' => $this->heading,
            'speed' => $this->speed,
            'recorded_at' => $this->recorded_at,
        ];
    }
}
