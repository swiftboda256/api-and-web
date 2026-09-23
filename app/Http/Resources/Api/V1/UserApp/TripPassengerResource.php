<?php

namespace App\Http\Resources\Api\V1\UserApp;

use App\Models\TripPassenger;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Flat, single-user shape matching the original (pre-unification) TripResource contract as
 * closely as the trip_passengers/delivery_details split allows, to minimise breaking changes
 * for already-integrated clients. Built from a TripPassenger anchor (the requesting
 * customer's own segment on a 'ride'/'ride_share' trip) -- 'id' is this passenger's own id
 * (matching what every other passenger-scoped endpoint keys off), 'trip_id' is the underlying
 * vehicle trip's id. 'rider'/'vehicle_type' describe the whole vehicle trip; every other
 * field is this passenger's own -- there's no per-passenger list, since a customer only ever
 * sees their own segment, never another rider's on a shared trip. See DeliveryResource for
 * the mirrored delivery-side version.
 *
 * @mixin TripPassenger
 */
class TripPassengerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $trip = $this->trip;
        $fareBreakdown = $this->fareBreakdown;

        return [
            'id' => $this->id,
            'trip_id' => $trip->id,
            'trip_number' => $trip->trip_number,
            'type' => $trip->type,
            'available_seats' => $trip->available_seats,
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
            'customer' => $this->relationLoaded('customer') && $this->customer ? [
                'id' => $this->customer->id,
                'name' => $this->customer->name,
                'phone' => $this->customer->phone,
            ] : null,
            'status' => $this->status,
            'seats_requested' => $this->seats_requested,
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
            'discount_percentage' => $fareBreakdown?->discount_percentage,
            'estimated_fare' => $fareBreakdown?->estimated_fare,
            'final_fare' => $fareBreakdown?->final_fare,
            'currency_code' => $fareBreakdown?->currency_code,
            'payment_method' => $fareBreakdown?->payment_method,
            'payment_status' => $fareBreakdown?->payment_status,
            'cancellation_reason' => $this->relationLoaded('cancellationReason') ? $this->cancellationReason?->label : null,
            'requested_at' => $this->requested_at,
            'matched_at' => $this->matched_at,
            'picked_up_at' => $this->picked_up_at,
            'dropped_off_at' => $this->dropped_off_at,
            'cancelled_at' => $this->cancelled_at,
        ];
    }
}
