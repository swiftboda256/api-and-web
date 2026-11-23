<?php

use App\Console\Commands\DispatchScheduledTrips;
use App\Console\Commands\ExpirePostedRideRequests;
use App\Console\Commands\ExpireRideShareJoinRequests;
use App\Console\Commands\ProcessTripDispatch;
use App\Console\Commands\ResolvePendingTransactions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Without this the sub-minute loop queries the cache about 40 times a second for pause and
// interrupt signals. schedule:pause and schedule:interrupt have no effect as a result.
Schedule::withoutInterruptionPolling();

// Overlap locks expire after five minutes so one left behind by a killed process
// can't block its command for the default 24 hours.
Schedule::command(DispatchScheduledTrips::class)->everyMinute()->withoutOverlapping(5);

// Runs inside the scheduler process: a Schedule::command() would boot a fresh
// PHP process every two seconds. The lock expires after a minute so a killed
// scheduler can't block polling for the default 24 hours.
Schedule::call(fn () => Artisan::call(ResolvePendingTransactions::class))
    ->name('transactions:resolve-pending')
    ->everyTwoSeconds()
    ->withoutOverlapping(1);

// Also runs inside the scheduler process, to avoid a fresh boot every five seconds.
Schedule::call(fn () => Artisan::call(ExpireRideShareJoinRequests::class))
    ->name('ride-share:expire-join-requests')
    ->everyFiveSeconds()
    ->withoutOverlapping(5);

Schedule::command(ExpirePostedRideRequests::class)->everyMinute()->withoutOverlapping(5);
Schedule::command(ProcessTripDispatch::class)->everyTenSeconds()->withoutOverlapping(5);
