<?php

namespace App\Console\Commands;

use App\Models\DeliveryStop;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Services\Checkout\CheckoutService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Backfills existing "legacy" trips onto the trip_passengers/delivery_details
 * architecture: every ride trip gets a trip_passengers row (+ 2 trip_stops), and every
 * delivery trip's existing delivery_details row gets its new customer/fare/pickup/dropoff
 * columns populated (+ 2 delivery_stops). ride_share/delivery_share trips are left alone --
 * they already create trip_passengers/delivery pooling rows live.
 *
 * Idempotent and safe to re-run: each side is gated on "not yet backfilled" (no
 * trip_passengers row for rides, a null sender_id for deliveries), so already-processed
 * trips are skipped. Purely additive -- never touches the legacy trips columns, which stay
 * in place until a separate, later migration drops them once this command reports zero
 * remaining trips of either kind.
 */
#[Signature('trips:backfill-passenger-model {--dry-run : Report what would change without writing anything}')]
#[Description('Backfill existing ride and delivery trips onto the trip_passengers/delivery_details architecture')]
class BackfillTripPassengerModel extends Command
{
    /**
     * @var array<string, string>
     */
    private const array STATUS_MAP = [
        'requested' => 'requested',
        'searching' => 'requested',
        'accepted' => 'matched',
        'arrived' => 'arrived_pickup',
        'in_progress' => 'picked_up',
        'completed' => 'dropped_off',
        'cancelled' => 'cancelled',
    ];

    public function handle(CheckoutService $checkout): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $rideBackfilled = 0;
        $deliveryBackfilled = 0;
        $deliveryMissingDetails = 0;

        Trip::query()
            ->where('type', 'ride')
            ->whereDoesntHave('passengers')
            ->with('zone')
            ->chunkById(100, function ($trips) use ($dryRun, $checkout, &$rideBackfilled): void {
                foreach ($trips as $trip) {
                    if (! $dryRun) {
                        $this->backfillRideTrip($trip, $checkout);
                    }

                    $rideBackfilled++;
                }
            });

        Trip::query()
            ->where('type', 'delivery')
            ->where(fn ($query) => $query
                ->whereDoesntHave('deliveries')
                ->orWhereHas('deliveries', fn ($query) => $query->whereNull('sender_id')))
            ->with('deliveries')
            ->chunkById(100, function ($trips) use ($dryRun, &$deliveryBackfilled, &$deliveryMissingDetails): void {
                foreach ($trips as $trip) {
                    if ($trip->deliveries->isEmpty()) {
                        $deliveryMissingDetails++;

                        continue;
                    }

                    if (! $dryRun) {
                        $this->backfillDeliveryTrip($trip);
                    }

                    $deliveryBackfilled++;
                }
            });

        $label = $dryRun ? 'Would backfill' : 'Backfilled';
        $this->info("{$label} {$rideBackfilled} ride trip(s) and {$deliveryBackfilled} delivery trip(s).");

        if ($deliveryMissingDetails > 0) {
            $this->warn("{$deliveryMissingDetails} delivery trip(s) have no delivery_details row at all -- skipped, needs manual investigation.");
        }

        $remainingRides = Trip::query()->where('type', 'ride')->whereDoesntHave('passengers')->count();
        $remainingDeliveries = Trip::query()
            ->where('type', 'delivery')
            ->where(fn ($query) => $query
                ->whereDoesntHave('deliveries')
                ->orWhereHas('deliveries', fn ($query) => $query->whereNull('sender_id')))
            ->count();

        $this->info("Remaining unbackfilled: {$remainingRides} ride trip(s), {$remainingDeliveries} delivery trip(s).");

        if (! $dryRun && $remainingRides === 0 && $remainingDeliveries === 0 && $deliveryMissingDetails === 0) {
            $this->info('All legacy trips are backfilled. Safe to run the legacy-column-drop migration.');
        }

        return self::SUCCESS;
    }

    private function backfillRideTrip(Trip $trip, CheckoutService $checkout): void
    {
        $fareComponents = $this->legacyFareComponents($trip, $checkout);

        DB::transaction(function () use ($trip, $fareComponents): void {
            $passenger = TripPassenger::query()->create([
                'trip_id' => $trip->id,
                'customer_id' => $trip->customer_id,
                'seats_requested' => 1,
                'status' => self::STATUS_MAP[$trip->status],
                'distance_km' => $trip->distance_km,
                'duration_minutes' => $trip->duration_minutes,
                'requested_at' => $trip->requested_at,
                'matched_at' => $trip->accepted_at,
                'picked_up_at' => $trip->started_at,
                'dropped_off_at' => $trip->completed_at,
                'cancelled_at' => $trip->cancelled_at,
                'cancelled_by' => $trip->cancelled_by,
                'cancellation_reason_id' => $trip->cancellation_reason_id,
            ]);

            TripFareBreakdown::query()->create([
                'trip_id' => $trip->id,
                'passenger_id' => $passenger->id,
                'customer_id' => $trip->customer_id,
                'base_fare' => $fareComponents['base_fare'] ?? null,
                'distance_fare' => $fareComponents['distance_fare'] ?? null,
                'time_fare' => $fareComponents['time_fare'] ?? null,
                'estimated_fare' => $trip->estimated_fare,
                'final_fare' => $trip->final_fare,
                'currency_code' => $trip->currency_code,
                'payment_method' => $trip->payment_method,
                'payment_status' => $trip->payment_status,
            ]);

            TripStop::query()->create([
                'trip_id' => $trip->id,
                'trip_passenger_id' => $passenger->id,
                'stop_type' => 'pickup',
                'seats_delta' => 1,
                'sequence' => 1,
                'location' => $trip->pickup_location,
                'address' => $trip->pickup_address,
                'arrived_at' => $this->pickupArrivedAt($trip),
            ]);

            TripStop::query()->create([
                'trip_id' => $trip->id,
                'trip_passenger_id' => $passenger->id,
                'stop_type' => 'dropoff',
                'seats_delta' => -1,
                'sequence' => 2,
                'location' => $trip->dropoff_location,
                'address' => $trip->dropoff_address,
                'arrived_at' => $this->dropoffArrivedAt($trip),
            ]);
        });
    }

    private function backfillDeliveryTrip(Trip $trip): void
    {
        DB::transaction(function () use ($trip): void {
            $delivery = $trip->deliveries->first();

            $delivery->update([
                'sender_id' => $trip->customer_id,
                'status' => self::STATUS_MAP[$trip->status],
                'pickup_location' => $trip->pickup_location,
                'pickup_address' => $trip->pickup_address,
                'dropoff_location' => $trip->dropoff_location,
                'dropoff_address' => $trip->dropoff_address,
                'distance_km' => $trip->distance_km,
                'duration_minutes' => $trip->duration_minutes,
                'estimated_fare' => $trip->estimated_fare,
                'final_fare' => $trip->final_fare,
                'currency_code' => $trip->currency_code,
                'payment_method' => $trip->payment_method,
                'payment_status' => $trip->payment_status,
                'requested_at' => $trip->requested_at,
                'matched_at' => $trip->accepted_at,
                'picked_up_at' => $trip->started_at,
                'dropped_off_at' => $trip->completed_at,
                'cancelled_at' => $trip->cancelled_at,
                'cancelled_by' => $trip->cancelled_by,
                'cancellation_reason_id' => $trip->cancellation_reason_id,
            ]);

            DeliveryStop::query()->create([
                'trip_id' => $trip->id,
                'delivery_details_id' => $delivery->id,
                'stop_type' => 'pickup',
                'sequence' => 1,
                'location' => $trip->pickup_location,
                'address' => $trip->pickup_address,
                'arrived_at' => $this->pickupArrivedAt($trip),
            ]);

            DeliveryStop::query()->create([
                'trip_id' => $trip->id,
                'delivery_details_id' => $delivery->id,
                'stop_type' => 'dropoff',
                'sequence' => 2,
                'location' => $trip->dropoff_location,
                'address' => $trip->dropoff_address,
                'arrived_at' => $this->dropoffArrivedAt($trip),
            ]);
        });
    }

    /**
     * Legacy trips never recorded their fare components separately, so they're recomputed
     * from the trip's zone pricing rule as it stood at requested_at (the rule's own
     * effective_from/effective_to window, not surge). Surge is deliberately left out --
     * no surge schedule is applied here, unlike calculateFare(). Null (components left
     * empty) when the zone/vehicle type/distance or an effective pricing rule can't be
     * resolved, rather than aborting the whole backfill.
     *
     * @return array{base_fare: float, distance_fare: float, time_fare: float}|null
     */
    private function legacyFareComponents(Trip $trip, CheckoutService $checkout): ?array
    {
        $zone = $trip->zone;

        if ($zone === null || $trip->vehicle_type_id === null || $trip->distance_km === null) {
            return null;
        }

        try {
            $pricingRule = $checkout->resolvePricingRule($zone->id, (int) $trip->vehicle_type_id, $trip->requested_at);
        } catch (ValidationException) {
            return null;
        }

        $distanceKm = (float) $trip->distance_km;
        $durationMinutes = $trip->duration_minutes ?? $checkout->durationMinutes($distanceKm);
        $chargeableDistanceKm = max(0.0, $distanceKm - (float) $pricingRule->vehicleType->free_distance_km);

        return [
            'base_fare' => (float) $pricingRule->base_fare,
            'distance_fare' => round((float) $pricingRule->per_km_rate * $chargeableDistanceKm, 2),
            'time_fare' => round((float) $pricingRule->per_minute_rate * $durationMinutes, 2),
        ];
    }

    private function pickupArrivedAt(Trip $trip): ?CarbonImmutable
    {
        if (! in_array($trip->status, ['arrived', 'in_progress', 'completed'], true)) {
            return null;
        }

        return $trip->arrived_at ?? $trip->started_at;
    }

    private function dropoffArrivedAt(Trip $trip): ?CarbonImmutable
    {
        return $trip->status === 'completed' ? $trip->completed_at : null;
    }
}
