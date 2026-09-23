<?php

namespace App\Http\Resources\Api\V1\RiderApp;

use App\Models\DeliveryDetails;
use App\Models\Trip;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Trip
 */
class RideResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Trips carry no pickup/dropoff/fare/customer of their own since the trip_passengers/
        // delivery_details unification -- the top-level fields below mirror what trips-level
        // columns always held anyway (the earliest/first-joined passenger or delivery's own
        // data). ride_share/delivery_share callers should use the 'ride_share'/'delivery'
        // block below for the full, accurate per-passenger breakdown.
        $primaryItem = in_array($this->type, ['ride', 'ride_share'], true)
            ? ($this->relationLoaded('passengers') ? $this->passengers->first() : null)
            : ($this->relationLoaded('deliveries') ? $this->deliveries->first() : null);

        $primaryCustomer = $primaryItem instanceof DeliveryDetails ? $primaryItem->sender : $primaryItem?->customer;
        $primaryPickupStop = $primaryItem?->stops->firstWhere('stop_type', 'pickup');
        $primaryDropoffStop = $primaryItem?->stops->firstWhere('stop_type', 'dropoff');

        // Ride/ride_share's fare/payment data lives on the passenger's fare breakdown now;
        // delivery keeps it on the delivery record directly (not moved there yet).
        $primaryFareBreakdown = $primaryItem instanceof DeliveryDetails ? null : $primaryItem?->fareBreakdown;

        // rider_earning/commission_amount are only known once a passenger settles (drops
        // off) -- delivery isn't wired up yet.
        $settledFareBreakdowns = $this->type === 'ride' || $this->type === 'ride_share'
            ? ($this->relationLoaded('passengers') ? $this->passengers->map(fn ($passenger) => $passenger->fareBreakdown)->filter(fn ($fareBreakdown) => $fareBreakdown?->rider_earning !== null) : null)
            : null;

        return [
            'id' => $this->id,
            'trip_number' => $this->trip_number,
            'type' => $this->type,
            'status' => $this->status,
            'customer' => $primaryCustomer ? [
                'id' => $primaryCustomer->id,
                'name' => $primaryCustomer->name,
                'phone' => $primaryCustomer->phone,
                'avatar_url' => $primaryCustomer->avatar_url,
            ] : null,
            'pickup' => $primaryPickupStop ? [
                'latitude' => $primaryPickupStop->location->getLatitude(),
                'longitude' => $primaryPickupStop->location->getLongitude(),
                'address' => $primaryPickupStop->address,
            ] : null,
            'dropoff' => $primaryDropoffStop ? [
                'latitude' => $primaryDropoffStop->location->getLatitude(),
                'longitude' => $primaryDropoffStop->location->getLongitude(),
                'address' => $primaryDropoffStop->address,
            ] : null,
            'vehicle_type' => $this->whenLoaded('vehicleType', fn () => [
                'id' => $this->vehicleType->id,
                'name' => $this->vehicleType->name,
                'code' => $this->vehicleType->code,
            ]),
            'distance_km' => $primaryItem?->distance_km,
            'duration_minutes' => $primaryItem?->duration_minutes,
            'estimated_fare' => $primaryItem instanceof DeliveryDetails
                ? app(CheckoutService::class)->roundFare((float) $primaryItem->estimated_fare)
                : ($primaryFareBreakdown ? app(CheckoutService::class)->roundFare((float) $primaryFareBreakdown->estimated_fare) : null),
            'final_fare' => $primaryItem instanceof DeliveryDetails ? $primaryItem->final_fare : $primaryFareBreakdown?->final_fare,
            'currency_code' => $primaryItem instanceof DeliveryDetails ? $primaryItem->currency_code : $primaryFareBreakdown?->currency_code,
            'payment_method' => $primaryItem instanceof DeliveryDetails ? $primaryItem->payment_method : $primaryFareBreakdown?->payment_method,
            'payment_status' => $primaryItem instanceof DeliveryDetails ? $primaryItem->payment_status : $primaryFareBreakdown?->payment_status,
            'rider_earning' => $settledFareBreakdowns && $settledFareBreakdowns->isNotEmpty() ? (float) $settledFareBreakdowns->sum('rider_earning') : null,
            'commission_amount' => $settledFareBreakdowns && $settledFareBreakdowns->isNotEmpty() ? (float) $settledFareBreakdowns->sum('commission_amount') : null,
            'ride_share' => $this->type === 'ride_share' ? [
                'available_seats' => $this->available_seats,
                'passenger_count' => $this->passenger_count,
                'route_polyline' => $this->route_polyline,
                'passengers' => $this->whenLoaded('passengers', fn () => $this->passengers->map(fn ($passenger) => [
                    'id' => $passenger->id,
                    'status' => $passenger->status,
                    'customer' => [
                        'id' => $passenger->customer->id,
                        'name' => $passenger->customer->name,
                        'phone' => $passenger->customer->phone,
                    ],
                    'pickup' => optional($passenger->stops->firstWhere('stop_type', 'pickup'), fn ($stop) => [
                        'latitude' => $stop->location->getLatitude(),
                        'longitude' => $stop->location->getLongitude(),
                        'address' => $stop->address,
                        'arrived_at' => $stop->arrived_at,
                    ]),
                    'dropoff' => optional($passenger->stops->firstWhere('stop_type', 'dropoff'), fn ($stop) => [
                        'latitude' => $stop->location->getLatitude(),
                        'longitude' => $stop->location->getLongitude(),
                        'address' => $stop->address,
                        'arrived_at' => $stop->arrived_at,
                    ]),
                    'estimated_fare' => $passenger->fareBreakdown?->estimated_fare,
                    'final_fare' => $passenger->fareBreakdown?->final_fare,
                ])->all()),
            ] : null,
            'delivery' => in_array($this->type, ['delivery', 'delivery_share'], true) ? [
                'available_cargo_weight_kg' => $this->available_cargo_weight_kg,
                'passenger_count' => $this->passenger_count,
                'route_polyline' => $this->route_polyline,
                'deliveries' => $this->whenLoaded('deliveries', fn () => $this->deliveries->map(fn ($delivery) => [
                    'id' => $delivery->id,
                    'status' => $delivery->status,
                    'sender' => [
                        'id' => $delivery->sender->id,
                        'name' => $delivery->sender->name,
                        'phone' => $delivery->sender->phone,
                    ],
                    'recipient_name' => $delivery->recipient_name,
                    'recipient_phone' => $delivery->recipient_phone,
                    'package_description' => $delivery->package_description,
                    'package_size' => $delivery->package_size,
                    'package_weight_kg' => $delivery->package_weight_kg,
                    'requires_signature' => $delivery->requires_signature,
                    'proof_of_delivery_photo' => $delivery->proof_of_delivery_photo,
                    'pickup' => optional($delivery->stops->firstWhere('stop_type', 'pickup'), fn ($stop) => [
                        'latitude' => $stop->location->getLatitude(),
                        'longitude' => $stop->location->getLongitude(),
                        'address' => $stop->address,
                        'arrived_at' => $stop->arrived_at,
                    ]),
                    'dropoff' => optional($delivery->stops->firstWhere('stop_type', 'dropoff'), fn ($stop) => [
                        'latitude' => $stop->location->getLatitude(),
                        'longitude' => $stop->location->getLongitude(),
                        'address' => $stop->address,
                        'arrived_at' => $stop->arrived_at,
                    ]),
                    'estimated_fare' => $delivery->estimated_fare,
                    'final_fare' => $delivery->final_fare,
                ])->all()),
            ] : null,
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
