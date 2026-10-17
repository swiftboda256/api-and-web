<?php

namespace App\Services\Trip;

use App\Models\Configuration;
use App\Models\DeliveryDetails;
use App\Models\RiderProfile;
use App\Models\Trip;
use App\Models\TripOffer;
use App\Models\TripPassenger;
use App\Services\Push\FcmGateway;
use App\Services\Push\PushMessage;
use App\Support\Geo;
use Carbon\CarbonInterface;
use Clickbar\Magellan\Data\Geometries\Point;
use Clickbar\Magellan\Database\PostgisFunctions\ST;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Finds a rider for a searching trip.
 *
 * A search sends a ringing job offer (ride_offer) to every eligible nearby rider, each
 * live for dispatch_offer_window_seconds. If nobody accepts in that window, the next round
 * offers the trip to riders who've become eligible since -- a rider is offered a trip at
 * most once per search. A rider can still accept from the new-trips list after their
 * offer stops ringing, while the trip is searching. After dispatch_search_timeout_minutes
 * with no rider, the trip is cancelled and the customer told no riders were found.
 *
 * Whenever an offer stops being live -- taken by another rider, the trip cancelled, or
 * expired -- that rider gets a silent ride_offer_cancelled push so the app stops ringing.
 *
 * Rounds, expiry and the timeout are driven by ProcessTripDispatch every few seconds.
 */
readonly class TripDispatchService
{
    private const array ACTIVE_BOOKING_STATUSES = ['requested', 'matched', 'arrived_pickup', 'picked_up', 'arrived_dropoff'];

    public function __construct(
        private FcmGateway $push,
        private TripNotifier $notifier,
    ) {}

    /**
     * Starts a new search for the trip (it must already be 'searching'). $excludedRiderId
     * -- a rider who just cancelled it -- isn't offered it again during this search.
     */
    public function startSearch(Trip $trip, ?int $excludedRiderId = null): void
    {
        $trip->update(['search_started_at' => now()]);

        if ($excludedRiderId !== null) {
            TripOffer::query()->create([
                'trip_id' => $trip->id,
                'rider_id' => $excludedRiderId,
                'status' => 'withdrawn',
                'offered_at' => now(),
                'expires_at' => now(),
                'closed_at' => now(),
            ]);
        }

        $this->offerRound($trip);
    }

    /**
     * Offers the trip to every eligible nearby rider not yet offered it in this search.
     * Returns how many riders were offered it.
     */
    public function offerRound(Trip $trip): int
    {
        $trip->refresh();

        if ($trip->status !== 'searching' || $trip->rider_id !== null || $trip->search_started_at === null) {
            return 0;
        }

        $booking = $this->notifier->primaryBooking($trip);
        $booking?->load($booking instanceof DeliveryDetails ? ['stops'] : ['stops', 'fareBreakdown']);
        $pickupStop = $booking?->stops->firstWhere('stop_type', 'pickup');

        // Nobody is waiting on this trip any more (its booking was cancelled): nothing to
        // offer. The search ends at its timeout.
        if ($booking === null || $booking->status !== 'requested' || $pickupStop?->location === null) {
            return 0;
        }

        $alreadyOffered = TripOffer::query()
            ->where('trip_id', $trip->id)
            ->where('offered_at', '>=', $trip->search_started_at)
            ->pluck('rider_id');

        $riders = $this->eligibleRiders($pickupStop->location, (int) $trip->vehicle_type_id)
            ->whereNotIn('user_id', $alreadyOffered)
            ->with('user')
            ->get();

        $expiresAt = now()->addSeconds((int) Configuration::get('dispatch_offer_window_seconds', 30));

        foreach ($riders as $riderProfile) {
            TripOffer::query()->create([
                'trip_id' => $trip->id,
                'rider_id' => $riderProfile->user_id,
                'status' => 'pending',
                'offered_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $this->push->sendToUser($riderProfile->user, $this->offerMessage($trip, $booking, $pickupStop->location, $pickupStop->address, $riderProfile, $expiresAt));
        }

        return $riders->count();
    }

    /**
     * Closes every live offer on the trip -- $acceptedRiderId's as accepted, the rest as
     * withdrawn -- and tells those riders to stop ringing. $reason: taken|cancelled.
     */
    public function closeOffers(Trip $trip, string $reason, ?int $acceptedRiderId = null): void
    {
        $offers = TripOffer::query()->where('trip_id', $trip->id)->where('status', 'pending')->with('rider')->get();

        foreach ($offers as $offer) {
            $accepted = $acceptedRiderId !== null && $offer->rider_id === $acceptedRiderId;

            $this->closeOffer($offer, $accepted ? 'accepted' : 'withdrawn', $accepted ? null : $reason);
        }
    }

    /**
     * One tick of the dispatch loop: expires offers past their window (and any left live on
     * a trip that's stopped searching), then runs the next round for searches whose offers
     * have all lapsed, or ends searches that have timed out.
     */
    public function processDue(): void
    {
        TripOffer::query()
            ->where('status', 'pending')
            ->where(fn ($query) => $query
                ->where('expires_at', '<=', now())
                ->orWhereHas('trip', fn ($query) => $query->where('status', '!=', 'searching')->orWhereNotNull('rider_id')))
            ->with(['rider', 'trip'])
            ->chunkById(100, function ($offers): void {
                foreach ($offers as $offer) {
                    $stillSearching = $offer->trip->status === 'searching' && $offer->trip->rider_id === null;

                    if ($stillSearching) {
                        $this->closeOffer($offer, 'expired', 'expired');
                    } else {
                        $this->closeOffer($offer, 'withdrawn', $offer->trip->rider_id !== null ? 'taken' : 'cancelled');
                    }
                }
            });

        $timeoutMinutes = (int) Configuration::get('dispatch_search_timeout_minutes', 5);

        Trip::query()
            ->where('status', 'searching')
            ->whereNull('rider_id')
            ->whereNotNull('search_started_at')
            ->chunkById(100, function ($trips) use ($timeoutMinutes): void {
                foreach ($trips as $trip) {
                    if ($trip->search_started_at->addMinutes($timeoutMinutes)->isPast()) {
                        $this->endSearchWithoutRider($trip);
                    } elseif (! TripOffer::query()->where('trip_id', $trip->id)->where('status', 'pending')->exists()) {
                        $this->offerRound($trip);
                    }
                }
            });
    }

    /**
     * Nobody accepted before the search timed out: the trip and its bookings are cancelled
     * (by no one) and each customer is told no riders were found.
     */
    public function endSearchWithoutRider(Trip $trip): void
    {
        $bookings = DB::transaction(function () use ($trip) {
            $ended = Trip::query()
                ->whereKey($trip->id)
                ->where('status', 'searching')
                ->whereNull('rider_id')
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            if ($ended === 0) {
                return collect();
            }

            $isRide = in_array($trip->type, ['ride', 'ride_share', 'posted_ride'], true);
            $query = $isRide ? TripPassenger::query()->with('customer') : DeliveryDetails::query()->with('sender');
            $bookings = $query->where('trip_id', $trip->id)->whereIn('status', self::ACTIVE_BOOKING_STATUSES)->get();

            ($isRide ? TripPassenger::query() : DeliveryDetails::query())
                ->whereKey($bookings->modelKeys())
                ->update(['status' => 'cancelled', 'cancelled_at' => now()]);

            return $bookings;
        });

        $this->closeOffers($trip, 'cancelled');

        foreach ($bookings as $booking) {
            $this->notifier->noRiders($booking);
        }
    }

    /**
     * Online riders with an approved vehicle of the trip's type, within the dispatch radius
     * of the pickup and their wallet limit.
     *
     * @return Builder<RiderProfile>
     */
    private function eligibleRiders(Point $pickup, int $vehicleTypeId): Builder
    {
        $radiusMeters = (float) Configuration::get('dispatch_radius_km', 5) * 1000;

        return RiderProfile::query()
            ->where('availability_status', 'online')
            ->whereNotNull('current_location')
            ->withinWalletLimit()
            ->whereHas('vehicle', fn ($query) => $query->where('vehicle_type_id', $vehicleTypeId)->where('status', 'approved'))
            ->where(ST::distanceSphere('current_location', $pickup), '<=', $radiusMeters);
    }

    private function offerMessage(Trip $trip, TripPassenger|DeliveryDetails $booking, Point $pickup, ?string $pickupAddress, RiderProfile $riderProfile, CarbonInterface $expiresAt): PushMessage
    {
        $dropoffAddress = $booking->stops->firstWhere('stop_type', 'dropoff')?->address;
        $fare = $booking instanceof DeliveryDetails ? $booking->estimated_fare : $booking->fareBreakdown?->estimated_fare;
        $currencyCode = $booking instanceof DeliveryDetails ? $booking->currency_code : $booking->fareBreakdown?->currency_code;
        $distanceKm = round(Geo::haversineKm($riderProfile->current_location, $pickup), 1);
        $isDelivery = in_array($trip->type, ['delivery', 'delivery_share'], true);

        $route = $pickupAddress && $dropoffAddress ? "{$pickupAddress} → {$dropoffAddress}" : ($pickupAddress ?: 'Pickup nearby');
        $farePart = $fare !== null ? "{$currencyCode} ".number_format((float) $fare) : null;

        $badge = TripOffer::query()
            ->where('rider_id', $riderProfile->user_id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->count();

        return new PushMessage(
            type: 'ride_offer',
            id: $trip->id,
            title: ($isDelivery ? 'New delivery job' : 'New job')." — {$distanceKm} km away",
            body: implode(' · ', array_filter([$route, $farePart])),
            data: [
                'trip_id' => $trip->id,
                'trip_type' => $trip->type,
                'pickup_latitude' => $pickup->getLatitude(),
                'pickup_longitude' => $pickup->getLongitude(),
                'pickup_address' => $pickupAddress,
                'dropoff_address' => $dropoffAddress,
                'distance_km' => $distanceKm,
                'estimated_fare' => $fare,
                'currency_code' => $currencyCode,
            ],
            sound: 'request_alert.caf',
            interruptionLevel: 'time-sensitive',
            ttlSeconds: max(1, (int) now()->diffInSeconds($expiresAt, true)),
            collapseKey: "ride_{$trip->id}",
            expiresAt: $expiresAt,
            badge: $badge,
            contentAvailable: true,
            // A ride offer repeats every dispatch round for every eligible rider -- it
            // shouldn't pile up as notification history, only ring/surface in the moment.
            persist: false,
        );
    }

    /**
     * $reason null: no push (the rider who accepted). Otherwise the rider is told to stop
     * ringing. Only closes an offer that's still pending, so it's never pushed twice.
     */
    private function closeOffer(TripOffer $offer, string $status, ?string $reason): void
    {
        $closed = TripOffer::query()
            ->whereKey($offer->id)
            ->where('status', 'pending')
            ->update(['status' => $status, 'closed_at' => now()]);

        if ($closed === 0 || $reason === null) {
            return;
        }

        $this->push->sendToUser($offer->rider, new PushMessage(
            type: 'ride_offer_cancelled',
            id: $offer->trip_id,
            data: ['trip_id' => $offer->trip_id, 'reason' => $reason],
            silent: true,
            ttlSeconds: 30,
        ));
    }
}
