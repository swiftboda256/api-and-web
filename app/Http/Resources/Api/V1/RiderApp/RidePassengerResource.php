<?php

namespace App\Http\Resources\Api\V1\RiderApp;

use App\Models\TripPassenger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TripPassenger
 */
class RidePassengerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pickupStop = $this->stops->firstWhere('stop_type', 'pickup');
        $dropoffStop = $this->stops->firstWhere('stop_type', 'dropoff');
        $fareBreakdown = $this->fareBreakdown;

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'status' => $this->status,
            'seats_requested' => $this->seats_requested,
            'customer' => $this->whenLoaded('customer', fn () => [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ]),
            'pickup' => $pickupStop ? [
                'latitude' => $pickupStop->location->getLatitude(),
                'longitude' => $pickupStop->location->getLongitude(),
                'address' => $pickupStop->address,
                'arrived_at' => $pickupStop->arrived_at,
            ] : null,
            'dropoff' => $dropoffStop ? [
                'latitude' => $dropoffStop->location->getLatitude(),
                'longitude' => $dropoffStop->location->getLongitude(),
                'address' => $dropoffStop->address,
                'arrived_at' => $dropoffStop->arrived_at,
            ] : null,
            'distance_km' => $this->distance_km,
            'duration_minutes' => $this->duration_minutes,
            'estimated_fare' => $fareBreakdown?->estimated_fare,
            'final_fare' => $fareBreakdown?->final_fare,
            'currency_code' => $fareBreakdown?->currency_code,
            'payment_method' => $fareBreakdown?->payment_method,
            'payment_status' => $fareBreakdown?->payment_status,
            'requested_at' => $this->requested_at,
            'matched_at' => $this->matched_at,
            'picked_up_at' => $this->picked_up_at,
            'dropped_off_at' => $this->dropped_off_at,
        ];
    }
}
