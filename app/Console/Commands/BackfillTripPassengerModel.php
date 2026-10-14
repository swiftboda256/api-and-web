<?php

namespace App\Console\Commands;

use App\Models\Configuration;
use App\Models\DeliveryDetails;
use App\Models\DeliveryStop;
use App\Models\Transaction;
use App\Models\Trip;
use App\Models\TripFareBreakdown;
use App\Models\TripPassenger;
use App\Models\TripStop;
use App\Services\Checkout\CheckoutService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Backfills existing "legacy" trips onto the trip_passengers/delivery_details
 * architecture: every ride trip gets a trip_passengers row (+ 2 trip_stops), and every
 * delivery trip's existing delivery_details row gets its new customer/fare/pickup/dropoff
 * columns populated (+ 2 delivery_stops). ride_share/delivery_share trips are left alone --
 * they already create trip_passengers/delivery pooling rows live.
 *
 * Also re-points every transaction still linked to a legacy trip (trip payments, payouts,
 * commissions, reversals) at the booking that replaces it, since payment confirmations
 * and booking history now look transactions up by TripPassenger/DeliveryDetails. And a
 * trip still searching when this runs either joins the new dispatch (requested within the
 * search timeout) or is cancelled as a search nobody answered.
 *
 * Idempotent and safe to re-run: each step is gated on "not yet done" (no trip_passengers
 * row for rides, a null sender_id for deliveries, a Trip reference on transactions), so
 * already-processed rows are skipped. Never touches the legacy trips columns, which stay
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

    private CheckoutService $checkoutService;

    /**
     * @var list<string>
     */
    private array $unresolvedFareTrips = [];

    private int $staleSearchesCancelled = 0;

    public function handle(CheckoutService $checkoutService): int
    {
        $this->checkoutService = $checkoutService;
        // The command instance can be reused within one process, so start every run clean.
        $this->unresolvedFareTrips = [];
        $this->staleSearchesCancelled = 0;
        $dryRun = (bool) $this->option('dry-run');

        $rideBackfilled = 0;
        $deliveryBackfilled = 0;
        $deliveryMissingDetails = 0;

        Trip::query()
            ->where('type', 'ride')
            ->whereDoesntHave('passengers')
            ->with(['zone', 'fareBreakdowns'])
            ->chunkById(100, function ($trips) use ($dryRun, &$rideBackfilled): void {
                foreach ($trips as $trip) {
                    if ($dryRun) {
                        $this->staleSearchesCancelled += (int) $this->isStaleSearch($trip);
                    } else {
                        $this->backfillRideTrip($trip);
                    }

                    $rideBackfilled++;
                }
            });

        Trip::query()
            ->where('type', 'delivery')
            ->where(fn ($query) => $query
                ->whereDoesntHave('deliveries')
                ->orWhereHas('deliveries', fn ($query) => $query->whereNull('sender_id')))
            ->with(['deliveries', 'fareBreakdowns'])
            ->chunkById(100, function ($trips) use ($dryRun, &$deliveryBackfilled, &$deliveryMissingDetails): void {
                foreach ($trips as $trip) {
                    if ($trip->deliveries->isEmpty()) {
                        $deliveryMissingDetails++;

                        continue;
                    }

                    if ($dryRun) {
                        $this->staleSearchesCancelled += (int) $this->isStaleSearch($trip);
                    } else {
                        $this->backfillDeliveryTrip($trip);
                    }

                    $deliveryBackfilled++;
                }
            });

        $label = $dryRun ? 'Would backfill' : 'Backfilled';
        $this->info("{$label} {$rideBackfilled} ride trip(s) and {$deliveryBackfilled} delivery trip(s).");

        [$repointed, $skippedTransactions] = $this->repointTripTransactions($dryRun);
        $this->info(($dryRun ? 'Would re-point' : 'Re-pointed')." {$repointed} trip transaction(s) to their booking.");

        if ($skippedTransactions > 0) {
            $this->warn("{$skippedTransactions} trip transaction(s) left linked to their trip -- the trip is not backfilled yet or does not have exactly one booking.");
        }

        if ($this->staleSearchesCancelled > 0) {
            $this->info(($dryRun ? 'Would cancel' : 'Cancelled')." {$this->staleSearchesCancelled} trip(s) left searching past the search timeout.");
        }

        if ($this->unresolvedFareTrips !== []) {
            $this->warn(count($this->unresolvedFareTrips).' ride trip(s) had no resolvable zone/pricing rule -- fare breakdown left without base/distance/time fare: '.implode(', ', $this->unresolvedFareTrips));
        }

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

    private function backfillRideTrip(Trip $trip): void
    {
        DB::transaction(function () use ($trip): void {
            $passenger = TripPassenger::query()->create([
                'trip_id' => $trip->id,
                'customer_id' => $trip->customer_id,
                'seats_requested' => 1,
                'status' => $this->bookingStatus($trip),
                'distance_km' => $trip->distance_km,
                'duration_minutes' => $trip->duration_minutes,
                'promo_code_id' => $trip->promo_code_id,
                'requested_at' => $trip->requested_at,
                'matched_at' => $trip->accepted_at,
                'picked_up_at' => $trip->started_at,
                'dropped_off_at' => $trip->completed_at,
                'cancelled_at' => $trip->cancelled_at,
                'cancelled_by' => $trip->cancelled_by,
                'cancellation_reason_id' => $trip->cancellation_reason_id,
            ]);

            // Legacy trips have a trip-level breakdown row written at booking, holding the
            // fare components actually charged -- reuse it for this passenger, keeping those
            // components as recorded. They're only recalculated for a trip that has none.
            $legacyBreakdown = $trip->fareBreakdowns->firstWhere('passenger_id', null);

            $fareBreakdown = $legacyBreakdown ?? new TripFareBreakdown([
                'trip_id' => $trip->id,
                'discount_amount' => $trip->discount_amount ?? 0,
                ...$this->recalculatedFareComponents($trip),
            ]);

            $fareBreakdown->fill([
                'passenger_id' => $passenger->id,
                'customer_id' => $trip->customer_id,
                'estimated_fare' => $trip->estimated_fare,
                'final_fare' => $trip->final_fare,
                'currency_code' => $trip->currency_code,
                'payment_method' => $trip->payment_method,
                'payment_status' => $trip->payment_status,
            ]);

            // Only meaningful when there are real components to total. Rounding only ever
            // rounds down, so a total below the fare charged means the recorded components
            // don't explain that fare -- fall back to the fare itself rather than record a
            // pre-rounding figure lower than what the customer paid.
            if ($fareBreakdown->base_fare !== null) {
                $estimated = $this->totalOfCharges(
                    (float) $fareBreakdown->base_fare,
                    (float) $fareBreakdown->distance_fare,
                    (float) $fareBreakdown->time_fare,
                    (float) $fareBreakdown->surge_amount,
                    discountAmount: (float) $fareBreakdown->discount_amount,
                );

                $fareBreakdown->estimated_fare_before_rounding = max($estimated, (float) $trip->estimated_fare);
                $fareBreakdown->final_fare_before_rounding = $trip->final_fare === null ? null
                    : max(round($estimated + (float) $fareBreakdown->cancellation_fee, 2), (float) $trip->final_fare);
            }

            $fareBreakdown->save();

            $this->resolveSearchingTrip($trip, $passenger);

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
                // Deliveries keep their fare on the delivery row itself; the base fare was
                // only ever recorded on the legacy trip-level breakdown row, which is left in
                // place as the record of the trip's commission and rider earning.
                'base_fare_amount' => $trip->fareBreakdowns->firstWhere('passenger_id', null)?->base_fare,
                'promo_code_id' => $trip->promo_code_id,
                'discount_amount' => $trip->discount_amount ?? 0,
                'status' => $this->bookingStatus($trip),
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

            $this->resolveSearchingTrip($trip, $delivery);
        });
    }

    /**
     * Legacy ride/delivery trips only ever had the statuses in STATUS_MAP ('open' is posted
     * rides only), so anything else means unexpected data -- fail loudly rather than guess.
     */
    private function bookingStatus(Trip $trip): string
    {
        return self::STATUS_MAP[$trip->status]
            ?? throw new \UnexpectedValueException("Trip {$trip->trip_number} has unexpected status '{$trip->status}'.");
    }

    /**
     * A trip still looking for a rider has no search start (the dispatch that sets one is
     * new), so it would never ring riders or time out. One requested within the search
     * timeout gets a search start of when it was requested, handing it to
     * ProcessTripDispatch like any other. One requested longer ago is a search nobody
     * answered -- it's cancelled with its booking, quietly, rather than ringing riders and
     * telling the customer about a request they made long ago.
     */
    private function resolveSearchingTrip(Trip $trip, TripPassenger|DeliveryDetails $booking): void
    {
        if ($trip->status !== 'searching' || $trip->search_started_at !== null) {
            return;
        }

        if (! $this->isStaleSearch($trip)) {
            Trip::query()->whereKey($trip->id)->update(['search_started_at' => $trip->requested_at]);

            return;
        }

        Trip::query()->whereKey($trip->id)->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
        $this->staleSearchesCancelled++;
    }

    /**
     * A trip left searching for longer than the search timeout -- nobody answered it.
     */
    private function isStaleSearch(Trip $trip): bool
    {
        return $trip->status === 'searching'
            && $trip->search_started_at === null
            && $trip->requested_at->addMinutes((int) Configuration::get('dispatch_search_timeout_minutes', 5))->isPast();
    }

    /**
     * Points every transaction still linked to a legacy trip at the one booking that now
     * stands for it -- the trip_passengers row of a 'ride', the delivery_details row of a
     * 'delivery'. Returns [re-pointed, skipped]; skipped transactions belong to a trip that
     * isn't backfilled yet or doesn't have exactly one booking, and are left untouched.
     *
     * @return array{0: int, 1: int}
     */
    private function repointTripTransactions(bool $dryRun): array
    {
        $repointed = 0;
        $skipped = 0;

        $tripIds = Transaction::query()
            ->where('reference_type', Trip::class)
            ->whereNotNull('reference_id')
            ->distinct()
            ->pluck('reference_id');

        foreach ($tripIds->chunk(100) as $chunk) {
            $trips = Trip::query()->whereKey($chunk)->withCount(['passengers', 'deliveries'])->with(['passengers', 'deliveries'])->get()->keyBy('id');

            foreach ($chunk as $tripId) {
                $trip = $trips->get($tripId);
                $query = Transaction::query()->where('reference_type', Trip::class)->where('reference_id', $tripId);

                // A dry run wrote no bookings above, so it counts what a real run would have.
                $eligible = match ($trip?->type) {
                    'ride' => $trip->passengers_count === 1 || ($dryRun && $trip->passengers_count === 0),
                    'delivery' => $trip->deliveries_count === 1 && ($dryRun || $trip->deliveries->first()->sender_id !== null),
                    default => false,
                };

                if (! $eligible) {
                    $skipped += $query->count();

                    continue;
                }

                if ($dryRun) {
                    $repointed += $query->count();

                    continue;
                }

                $booking = $trip->type === 'ride' ? $trip->passengers->first() : $trip->deliveries->first();
                $repointed += $query->update(['reference_type' => $booking::class, 'reference_id' => $booking->id]);
            }
        }

        return [$repointed, $skipped];
    }

    private function totalOfCharges(
        float $baseFare,
        float $distanceFare,
        float $timeFare,
        float $surgeAmount = 0.0,
        float $cancellationFee = 0.0,
        float $discountAmount = 0.0,
    ): float {
        return round($baseFare + $distanceFare + $timeFare + $surgeAmount + $cancellationFee - $discountAmount, 2);
    }

    /**
     * Recomputes base/distance/time fare with CheckoutService::calculateFare() from the
     * zone's current pricing rule. Surge is never applied, so surge_multiplier/surge_amount
     * come back as 1/0. Returns nothing (leaves the columns unset) when the zone or pricing
     * rule can't be resolved.
     *
     * @return array{base_fare?: float, distance_fare?: float, time_fare?: float, surge_multiplier?: float, surge_amount?: float}
     */
    private function recalculatedFareComponents(Trip $trip): array
    {
        if (! $trip->zone || ! $trip->vehicle_type_id || $trip->distance_km === null) {
            $this->unresolvedFareTrips[] = $trip->trip_number;

            return [];
        }

        $pricingRule = $this->checkoutService->findPricingRule((int) $trip->zone_id, (int) $trip->vehicle_type_id);

        if (! $pricingRule) {
            $this->unresolvedFareTrips[] = $trip->trip_number;

            return [];
        }

        $distanceKm = (float) $trip->distance_km;

        $fare = $this->checkoutService->calculateFare(
            $pricingRule,
            $trip->zone,
            (int) $trip->vehicle_type_id,
            $distanceKm,
            $trip->duration_minutes ?? $this->checkoutService->durationMinutes($distanceKm),
            now(),
            applySurge: false,
        );

        return Arr::only($fare, ['base_fare', 'distance_fare', 'time_fare', 'surge_multiplier', 'surge_amount']);
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
