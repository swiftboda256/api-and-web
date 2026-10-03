<?php

namespace App\Console\Commands;

use App\Models\TripPassenger;
use App\Services\Trip\PostedRideService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

#[Signature('posted-ride:expire-requests')]
#[Description('Expire posted-ride seat bookings the driver did not approve in time, releasing their seats')]
class ExpirePostedRideRequests extends Command
{
    public function handle(PostedRideService $postedRides): int
    {
        $count = 0;

        TripPassenger::query()
            ->where('status', 'pending_approval')
            ->where('request_expires_at', '<=', now())
            ->whereHas('trip', fn ($query) => $query->where('type', 'posted_ride'))
            ->chunkById(100, function ($passengers) use ($postedRides, &$count): void {
                foreach ($passengers as $passenger) {
                    try {
                        $postedRides->expire($passenger);
                        $count++;
                    } catch (Throwable $e) {
                        Log::error('posted_ride.request_expiry_failed', [
                            'trip_passenger_id' => $passenger->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            });

        $this->info("Expired {$count} posted-ride booking request(s).");

        return self::SUCCESS;
    }
}
