<?php

namespace App\Services\Trip;

use App\Models\DeliveryDetails;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\User;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;

/**
 * Trip lifecycle pushes, one method per event, so each event's type, sound and ids are
 * defined in one place.
 *
 * Customer-app pushes are about one booking: their id is the booking id (what
 * GET user-app/trips/{id} takes), with trip_id and trip_passenger_id/delivery_details_id
 * alongside. Rider-app pushes are about the vehicle trip: their id is the trip id.
 */
readonly class TripNotifier
{
    public function __construct(
        private FcmGateway $push,
    ) {}

    public function riderAssigned(TripPassenger|DeliveryDetails $booking, ?User $rider = null): void
    {
        $name = $rider?->name;

        $this->toCustomer($booking, 'trip_rider_assigned',
            $booking instanceof DeliveryDetails ? 'A rider is on the way' : 'Your rider is on the way',
            $name ? "{$name} has accepted your trip and is heading to the pickup point." : 'Your rider has accepted your trip and is heading to the pickup point.',
            ['status' => 'matched'],
            interruptionLevel: 'time-sensitive',
        );
    }

    public function riderArrived(TripPassenger|DeliveryDetails $booking): void
    {
        $this->toCustomer($booking, 'trip_rider_arrived', 'Your rider has arrived', 'Your rider is waiting at the pickup point.',
            ['status' => 'arrived_pickup'],
            sound: 'trip_alert.caf',
            interruptionLevel: 'time-sensitive',
        );
    }

    public function started(TripPassenger|DeliveryDetails $booking): void
    {
        $isDelivery = $booking instanceof DeliveryDetails;

        $this->toCustomer($booking, $isDelivery ? 'delivery_picked_up' : 'trip_started',
            $isDelivery ? 'Your delivery is on the way' : 'Your trip has started',
            $isDelivery ? 'Your package has been picked up and is on its way.' : 'Enjoy your ride.',
            ['status' => 'picked_up'],
        );
    }

    /**
     * Rides only -- see NearDestinationService.
     */
    public function nearDestination(TripPassenger $booking, int $radiusMeters): void
    {
        $this->toCustomer($booking, 'trip_near_destination', 'Almost there',
            "About {$radiusMeters} m to your drop-off. Get ready to step off.",
            sound: 'trip_alert.caf',
            interruptionLevel: 'time-sensitive',
        );
    }

    public function completed(TripPassenger|DeliveryDetails $booking): void
    {
        if ($booking instanceof DeliveryDetails) {
            $this->toCustomer($booking, 'delivery_delivered', 'Delivery completed', 'Your package has been delivered.', ['status' => 'dropped_off']);

            return;
        }

        $this->toCustomer($booking, 'trip_completed', 'You have arrived', 'You have reached your destination. Thank you for riding with us.',
            ['status' => 'dropped_off'],
        );
    }

    /**
     * The rider cancelled an accepted trip; it's searching for another rider.
     */
    public function riderCancelled(TripPassenger|DeliveryDetails $booking): void
    {
        $this->toCustomer($booking, 'trip_rider_cancelled', 'Your rider cancelled', 'Your rider cancelled the trip. We are finding you another rider.',
            ['status' => 'searching'],
            interruptionLevel: 'time-sensitive',
        );
    }

    /**
     * The rider cancelled a trip already under way; the booking is cancelled, not re-searched.
     */
    public function tripCancelled(TripPassenger|DeliveryDetails $booking): void
    {
        $this->toCustomer($booking, 'trip_cancelled', 'Your trip was cancelled', 'Your rider cancelled the trip. You have not been charged.',
            ['status' => 'cancelled'],
            interruptionLevel: 'time-sensitive',
        );
    }

    /**
     * Nobody accepted the trip before the search timed out; the booking is cancelled.
     */
    public function noRiders(TripPassenger|DeliveryDetails $booking): void
    {
        $this->toCustomer($booking, 'trip_no_riders', 'No riders available', 'We could not find a rider for your trip. Please try again.',
            ['status' => 'cancelled'],
        );
    }

    public function paymentReceived(TripPassenger|DeliveryDetails $booking, string $currencyCode, float $amount): void
    {
        $this->toCustomer($booking, 'payment_received', 'Payment received',
            "Your rider has received your cash payment of {$currencyCode} ".number_format($amount).'. Thank you for riding with us.',
            ['payment_status' => 'paid'],
        );
    }

    /**
     * To the rider: the customer cancelled their booking on the rider's trip.
     */
    public function bookingCancelled(TripPassenger|DeliveryDetails $booking): void
    {
        $trip = $booking->trip;
        $isDelivery = $booking instanceof DeliveryDetails;

        $this->push->sendToUser($trip->rider, new PushMessage(
            type: 'booking_cancelled',
            id: $trip->id,
            title: $isDelivery ? 'Delivery cancelled' : 'Ride cancelled',
            body: $isDelivery ? 'The sender cancelled their delivery.' : 'The passenger cancelled their ride.',
            data: [
                'trip_id' => $trip->id,
                ...$this->bookingIds($booking),
                'status' => 'cancelled',
                'trip_status' => (string) $trip->fresh()?->status,
            ],
        ));
    }

    /**
     * The trip's first (or only) booking -- the one a solo trip's lifecycle pushes are about.
     */
    public function primaryBooking(Trip $trip): TripPassenger|DeliveryDetails|null
    {
        return in_array($trip->type, ['ride', 'ride_share', 'posted_ride'], true)
            ? TripPassenger::query()->where('trip_id', $trip->id)->oldest('requested_at')->first()
            : DeliveryDetails::query()->where('trip_id', $trip->id)->oldest('requested_at')->first();
    }

    /**
     * @param  array<non-empty-string, string|int|float|null>  $data
     */
    private function toCustomer(TripPassenger|DeliveryDetails $booking, string $type, string $title, string $body, array $data = [], string $sound = 'default', ?string $interruptionLevel = null): void
    {
        $customer = $booking instanceof DeliveryDetails ? $booking->sender : $booking->customer;

        $this->push->sendToUser($customer, new PushMessage(
            type: $type,
            id: $booking->id,
            title: $title,
            body: $body,
            data: [
                'trip_id' => $booking->trip_id,
                ...$this->bookingIds($booking),
                ...$data,
            ],
            sound: $sound,
            interruptionLevel: $interruptionLevel,
        ));
    }

    /**
     * @return array<non-empty-string, int>
     */
    private function bookingIds(TripPassenger|DeliveryDetails $booking): array
    {
        return $booking instanceof DeliveryDetails
            ? ['delivery_details_id' => $booking->id]
            : ['trip_passenger_id' => $booking->id];
    }
}
