<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An open posted ride as a customer browsing for seats sees it -- the driver's route, date
 * and per-seat fare, plus enough about the driver and vehicle to choose one. Once booked,
 * the customer's own booking is a TripPassengerResource like any other ride.
 *
 * @mixin Trip
 */
class PostedRideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'trip_number' => $this->trip_number,
            'type' => $this->type,
            'status' => $this->status,
            'departs_at' => $this->departs_at,
            'origin' => [
                'latitude' => $this->origin_location?->getLatitude(),
                'longitude' => $this->origin_location?->getLongitude(),
                'address' => $this->origin_address,
            ],
            'destination' => [
                'latitude' => $this->destination_location?->getLatitude(),
                'longitude' => $this->destination_location?->getLongitude(),
                'address' => $this->destination_address,
            ],
            'distance_km' => $this->route_distance_km,
            'duration_minutes' => $this->route_duration_minutes,
            'seat_fare' => $this->seat_fare,
            'currency_code' => $this->zone?->currency_code,
            'available_seats' => $this->available_seats,
            'rider' => $this->rider ? [
                'name' => $this->rider->first_name,
                'rider_ref' => $this->rider->riderProfile?->rider_ref,
                'gender' => $this->rider->riderProfile?->gender,
                'rating_avg' => $this->rider->rating_avg,
                'rating_count' => $this->rider->rating_count,
            ] : null,
            'vehicle_type' => $this->vehicleType ? [
                'id' => $this->vehicleType->id,
                'name' => $this->vehicleType->name,
                'code' => $this->vehicleType->code,
            ] : null,
            'vehicle' => $this->vehicle ? [
                'color' => $this->vehicle->color,
                'plate_number' => $this->vehicle->plate_number,
            ] : null,
        ];
    }
}
