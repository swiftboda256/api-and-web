<?php

use App\Console\Commands\DispatchScheduledTrips;
use App\Console\Commands\ResolvePendingYoTransactions;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(DispatchScheduledTrips::class)->everyMinute()->withoutOverlapping();
Schedule::command(ResolvePendingYoTransactions::class)->everyThirtySeconds()->withoutOverlapping();
