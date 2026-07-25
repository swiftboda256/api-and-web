<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\Trip;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trip
 */
class TripResource extends JsonResource
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
            'pickup' => [
                'latitude' => $this->pickup_location->getLatitude(),
                'longitude' => $this->pickup_location->getLongitude(),
                'address' => $this->pickup_address,
            ],
            'dropoff' => [
                'latitude' => $this->dropoff_location->getLatitude(),
                'longitude' => $this->dropoff_location->getLongitude(),
                'address' => $this->dropoff_address,
            ],
            'vehicle_type' => $this->whenLoaded('vehicleType', fn () => [
                'id' => $this->vehicleType->id,
                'name' => $this->vehicleType->name,
                'code' => $this->vehicleType->code,
            ]),
            'distance_km' => $this->distance_km,
            'duration_minutes' => $this->duration_minutes,
            'estimated_fare' => $this->estimated_fare,
            'final_fare' => $this->final_fare,
            'currency_code' => $this->currency_code,
            'discount_amount' => $this->discount_amount,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'fare_breakdown' => $this->whenLoaded('fareBreakdown', fn () => [
                'base_fare' => $this->fareBreakdown->base_fare,
                'distance_fare' => $this->fareBreakdown->distance_fare,
                'time_fare' => $this->fareBreakdown->time_fare,
                'surge_multiplier' => $this->fareBreakdown->surge_multiplier,
                'surge_amount' => $this->fareBreakdown->surge_amount,
                'discount_amount' => $this->fareBreakdown->discount_amount,
                'cancellation_fee' => $this->fareBreakdown->cancellation_fee,
                'total' => $this->fareBreakdown->total,
            ]),
            'delivery_details' => $this->whenLoaded('deliveryDetails', fn () => [
                'recipient_name' => $this->deliveryDetails->recipient_name,
                'recipient_phone' => $this->deliveryDetails->recipient_phone,
                'package_description' => $this->deliveryDetails->package_description,
                'package_size' => $this->deliveryDetails->package_size,
                'package_weight_kg' => $this->deliveryDetails->package_weight_kg,
                'requires_signature' => $this->deliveryDetails->requires_signature,
            ]),
            'cancellation_reason' => $this->whenLoaded('cancellationReason', fn () => $this->cancellationReason?->label),
            'requested_at' => $this->requested_at,
            'accepted_at' => $this->accepted_at,
            'arrived_at' => $this->arrived_at,
            'started_at' => $this->started_at,
            'completed_at' => $this->completed_at,
            'cancelled_at' => $this->cancelled_at,
        ];
    }
}
