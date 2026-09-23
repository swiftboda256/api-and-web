<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\DeliveryDetails;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Delivery-side mirror of TripPassengerResource -- flat, single-user shape. Built from a
 * DeliveryDetails anchor (the requesting sender's own segment on a 'delivery'/
 * 'delivery_share' trip) -- 'id' is this delivery's own id, 'trip_id' is the underlying
 * vehicle trip's id. 'rider'/'vehicle_type' describe the whole vehicle trip; every other
 * field is this delivery's own -- there's no per-delivery list, since a sender only ever
 * sees their own package, never another sender's on a pooled-delivery trip.
 *
 * @mixin DeliveryDetails
 */
class DeliveryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $trip = $this->trip;

        return [
            'id' => $this->id,
            'trip_id' => $trip->id,
            'trip_number' => $trip->trip_number,
            'type' => $trip->type,
            'available_cargo_weight_kg' => $trip->available_cargo_weight_kg,
            'passenger_count' => $trip->passenger_count,
            'accepted_at' => $trip->accepted_at,
            'arrived_at' => $trip->arrived_at,
            'started_at' => $trip->started_at,
            'completed_at' => $trip->completed_at,
            'rider' => $trip->relationLoaded('rider') && $trip->rider ? [
                'name' => $trip->rider->name,
                'rider_ref' => $trip->rider->riderProfile?->rider_ref,
                'gender' => $trip->rider->riderProfile?->gender,
                'phone' => $trip->rider->phone,
                'current_location' => [
                    'latitude' => $trip->rider->riderProfile?->current_location?->getLatitude(),
                    'longitude' => $trip->rider->riderProfile?->current_location?->getLongitude(),
                ],
            ] : null,
            'vehicle_type' => $trip->relationLoaded('vehicleType') && $trip->vehicleType ? [
                'id' => $trip->vehicleType->id,
                'name' => $trip->vehicleType->name,
                'code' => $trip->vehicleType->code,
            ] : null,
            'sender' => $this->relationLoaded('sender') && $this->sender ? [
                'id' => $this->sender->id,
                'name' => $this->sender->name,
                'phone' => $this->sender->phone,
            ] : null,
            'status' => $this->status,
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'package_description' => $this->package_description,
            'package_size' => $this->package_size,
            'package_weight_kg' => $this->package_weight_kg,
            'requires_signature' => $this->requires_signature,
            'proof_of_delivery_photo' => $this->proof_of_delivery_photo,
            'delivered_to_name' => $this->delivered_to_name,
            'delivery_notes' => $this->delivery_notes,
            'pickup' => optional($this->stops->firstWhere('stop_type', 'pickup'), fn ($stop) => [
                'latitude' => $stop->location->getLatitude(),
                'longitude' => $stop->location->getLongitude(),
                'address' => $stop->address,
                'arrived_at' => $stop->arrived_at,
            ]),
            'dropoff' => optional($this->stops->firstWhere('stop_type', 'dropoff'), fn ($stop) => [
                'latitude' => $stop->location->getLatitude(),
                'longitude' => $stop->location->getLongitude(),
                'address' => $stop->address,
                'arrived_at' => $stop->arrived_at,
            ]),
            'distance_km' => $this->distance_km,
            'duration_minutes' => $this->duration_minutes,
            'discount_percentage' => $this->discount_percentage,
            'estimated_fare' => $this->estimated_fare,
            'final_fare' => $this->final_fare,
            'currency_code' => $this->currency_code,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'cancellation_reason' => $this->relationLoaded('cancellationReason') ? $this->cancellationReason?->label : null,
            'requested_at' => $this->requested_at,
            'matched_at' => $this->matched_at,
            'picked_up_at' => $this->picked_up_at,
            'dropped_off_at' => $this->dropped_off_at,
            'cancelled_at' => $this->cancelled_at,
        ];
    }
}
