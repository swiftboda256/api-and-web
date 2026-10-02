<?php

namespace App\Services\Trip;

use App\Models\TripPassenger;
use App\Models\User;
use App\Models\Zone;
use Clickbar\Magellan\Data\Geometries\Point;
use LogicException;

/**
 * A customer's ride-share request, carried across offers to successive drivers until one
 * accepts or it falls back to a brand-new trip.
 */
readonly class RideShareRequest
{
    public function __construct(
        public User $customer,
        public Zone $zone,
        public int $vehicleTypeId,
        public Point $pickup,
        public ?string $pickupAddress,
        public Point $dropoff,
        public ?string $dropoffAddress,
        public int $seatsRequested,
        public float $requestedDistanceKm,
        public string $paymentMethod,
    ) {}

    /**
     * Rebuilds the request from a pending passenger -- their pickup/dropoff stops and
     * current trip -- to re-offer it after a decline/expiry.
     */
    public static function fromPassenger(TripPassenger $passenger): self
    {
        $trip = $passenger->trip;
        $pickupStop = $passenger->stops->firstWhere('stop_type', 'pickup');
        $dropoffStop = $passenger->stops->firstWhere('stop_type', 'dropoff');

        if ($pickupStop === null || $dropoffStop === null) {
            throw new LogicException("Ride-share passenger {$passenger->id} has no pickup/dropoff stops to re-offer.");
        }

        return new self(
            customer: $passenger->customer,
            zone: $trip->zone,
            vehicleTypeId: (int) $trip->vehicle_type_id,
            pickup: $pickupStop->location,
            pickupAddress: $pickupStop->address,
            dropoff: $dropoffStop->location,
            dropoffAddress: $dropoffStop->address,
            seatsRequested: $passenger->seats_requested,
            // The pickup -> dropoff leg measured when it was last offered.
            requestedDistanceKm: (float) $passenger->distance_km,
            paymentMethod: (string) $passenger->fareBreakdown?->payment_method,
        );
    }
}
