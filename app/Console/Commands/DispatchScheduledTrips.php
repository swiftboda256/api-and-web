<?php

namespace App\Console\Commands;

use App\Jobs\DispatchScheduledTripJob;
use App\Models\Trip;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trips:dispatch-scheduled')]
#[Description('Dispatch scheduled trips to nearby riders once their requested time is due')]
class DispatchScheduledTrips extends Command
{
    public function handle(): int
    {
        $count = 0;

        Trip::query()
            ->where('status', 'requested')
            ->where('requested_at', '<=', now())
            ->select('id')
            ->chunkById(100, function ($trips) use (&$count): void {
                foreach ($trips as $trip) {
                    DispatchScheduledTripJob::dispatch($trip->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} scheduled trip(s) for dispatch.");

        return self::SUCCESS;
    }
}
