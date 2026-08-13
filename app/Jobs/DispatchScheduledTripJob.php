<?php

namespace App\Jobs;

use App\Models\Trip;
use App\Services\Trip\TripService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchScheduledTripJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $tripId,
    ) {}

    public function handle(TripService $tripService): void
    {
        $claimed = Trip::query()
            ->where('id', $this->tripId)
            ->where('status', 'requested')
            ->update(['status' => 'searching']);

        if ($claimed === 0) {
            return;
        }

        $trip = Trip::query()->find($this->tripId);

        if (! $trip) {
            return;
        }

        try {
            $tripService->dispatchDueTrip($trip);
        } catch (Throwable $e) {
            Log::error('trip.scheduled_dispatch_failed', [
                'trip_id' => $trip->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
