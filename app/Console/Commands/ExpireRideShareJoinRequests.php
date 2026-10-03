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

        $this->info("Expired and re-offered {$count} ride-share join request(s).");

        return self::SUCCESS;
    }
}
