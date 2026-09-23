<?php

namespace App\Http\Resources\Api\V1\RiderApp;

use App\Models\DeliveryDetails;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Driver-facing view of a single delivery on a delivery or pooled-delivery trip. Mirrors
 * RidePassengerResource exactly, for the delivery side of the same unification.
 *
 * @mixin DeliveryDetails
 */
class RideDeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $pickupStop = $this->stops->firstWhere('stop_type', 'pickup');
        $dropoffStop = $this->stops->firstWhere('stop_type', 'dropoff');

        return [
            'id' => $this->id,
            'trip_id' => $this->trip_id,
            'status' => $this->status,
            'sender' => $this->whenLoaded('sender', fn () => [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'phone' => $this->sender->phone,
            ]),
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'package_description' => $this->package_description,
            'package_size' => $this->package_size,
            'package_weight_kg' => $this->package_weight_kg,
            'requires_signature' => $this->requires_signature,
            'proof_of_delivery_photo' => $this->proof_of_delivery_photo,
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
            'estimated_fare' => $this->estimated_fare,
            'final_fare' => $this->final_fare,
            'currency_code' => $this->currency_code,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'requested_at' => $this->requested_at,
            'matched_at' => $this->matched_at,
            'picked_up_at' => $this->picked_up_at,
            'dropped_off_at' => $this->dropped_off_at,
        ];
    }
}
