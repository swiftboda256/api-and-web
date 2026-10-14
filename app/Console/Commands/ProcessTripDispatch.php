<?php

namespace App\Console\Commands;

use App\Services\Trip\TripDispatchService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trips:process-dispatch')]
#[Description('Expire lapsed job offers, run the next offer round for searching trips, and end searches that found no rider')]
class ProcessTripDispatch extends Command
{
    public function handle(TripDispatchService $dispatch): int
    {
        $dispatch->processDue();

        return self::SUCCESS;
    }
}
