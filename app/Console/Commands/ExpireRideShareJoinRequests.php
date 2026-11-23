<?php

namespace App\Console\Commands;

use App\Models\TripPassenger;
use App\Services\Trip\RideShareMatchingService;
use App\Services\Trip\TripService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('ride-share:expire-join-requests')]
#[Description('Expire ride-share join requests the driver did not answer in time (or whose trip has ended) and re-offer them')]
class ExpireRideShareJoinRequests extends Command
{
    public function handle(RideShareMatchingService $matching, TripService $tripService): int
    {
        $count = 0;

        // Also sweeps requests whose trip finished or was cancelled while they waited --
        // no point holding the customer until the timeout for a driver who can't accept.
        TripPassenger::query()
            ->where('status', 'pending_approval')
            ->whereNotNull('request_expires_at')
            // Posted-ride requests expire through ExpirePostedRideRequests -- they're never
            // re-offered, and their trip is 'open' rather than 'in_progress' while pending.
            ->whereHas('trip', fn ($query) => $query->where('type', 'ride_share'))
            ->where(fn ($query) => $query
                ->where('request_expires_at', '<=', now())
                ->orWhereHas('trip', fn ($query) => $query->where('status', '!=', 'in_progress')))
            ->chunkById(100, function ($passengers) use ($matching, $tripService, &$count): void {
                foreach ($passengers as $passenger) {
                    try {
                        if ($matching->release($passenger)) {
                            $tripService->reofferRideShareRequest($passenger);
                            $count++;
                        }
                    } catch (Throwable $e) {
                        Log::error('ride_share.join_request_expiry_failed', [
                            'trip_passenger_id' => $passenger->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $count += $this->reofferStranded($tripService);

        $this->info("Expired and re-offered {$count} ride-share join request(s).");

        return self::SUCCESS;
    }

    /**
     * Retries passengers released from a trip whose re-offer then failed (an error, or no
     * pricing rule): they have no expiry left for the sweep above to find. Waits a minute
     * between attempts, leaving room for a decline's own re-offer to finish.
     */
    private function reofferStranded(TripService $tripService): int
    {
        $count = 0;
        $stranded = fn () => TripPassenger::query()
            ->where('status', 'pending_approval')
            ->whereNull('request_expires_at')
            ->whereNotNull('declined_trip_ids')
            ->where('updated_at', '<=', now()->subMinute())
            ->whereHas('trip', fn ($query) => $query->where('type', 'ride_share'));

        $stranded()->chunkById(100, function ($passengers) use ($stranded, $tripService, &$count): void {
            foreach ($passengers as $passenger) {
                // Claimed by touching updated_at, which also spaces out the retries.
                if ($stranded()->whereKey($passenger->id)->update(['updated_at' => now()]) === 0) {
                    continue;
                }

                try {
                    $tripService->reofferRideShareRequest($passenger);
                    $count++;
                } catch (Throwable $e) {
                    Log::error('ride_share.stranded_reoffer_failed', [
                        'trip_passenger_id' => $passenger->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });

        return $count;
    }
}
