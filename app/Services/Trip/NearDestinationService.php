<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\Trip;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Support\Geo;
use Clickbar\Magellan\Data\Geometries\Point;

/**
 * Sends each riding passenger one "almost there" push (trip_near_destination) when the
 * rider's latest location is within near_destination_radius_meters of their drop-off.
 * Rides only, not deliveries. Checked on every location ping the rider app sends.
 */
readonly class NearDestinationService
{
    public function __construct(
        private TripNotifier $notifier,
    ) {}

    /**
     * Checks every in-progress ride the rider is driving.
     */
    public function checkRider(int $riderId, Point $location): void
    {
        Trip::query()
            ->where('rider_id', $riderId)
            ->where('status', 'in_progress')
            ->whereIn('type', ['ride', 'ride_share', 'posted_ride'])
            ->get()
            ->each(fn (Trip $trip) => $this->check($trip, $location));
    }

    public function check(Trip $trip, Point $location): void
    {
        if ($trip->status !== 'in_progress' || ! in_array($trip->type, ['ride', 'ride_share', 'posted_ride'], true)) {
            return;
        }

        $radiusMeters = (int) Configuration::get('near_destination_radius_meters', 400);

        $passengers = TripPassenger::query()
            ->where('trip_id', $trip->id)
            ->whereIn('status', ['picked_up', 'arrived_dropoff'])
            ->whereNull('near_destination_notified_at')
            ->with('customer')
            ->get();

        foreach ($passengers as $passenger) {
            $dropoff = TripStop::query()
                ->where('trip_passenger_id', $passenger->id)
                ->where('stop_type', 'dropoff')
                ->value('location');

            if (! $dropoff instanceof Point || Geo::haversineKm($location, $dropoff) * 1000 > $radiusMeters) {
                continue;
            }

            // Claimed atomically so two pings arriving together can't both send it.
            $claimed = TripPassenger::query()
                ->whereKey($passenger->id)
                ->whereNull('near_destination_notified_at')
                ->update(['near_destination_notified_at' => now()]);

            if ($claimed === 1) {
                $this->notifier->nearDestination($passenger, $radiusMeters);
            }
        }
    }
}
