<?php

namespace App\Console\Commands;

use App\Models\RiderProfile;
use App\Services\Rider\RiderProfileService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('riders:update-references')]
#[Description('Backfill existing rider references to the SWFT<district>0000 format, skipping riders already on it')]
class UpdateRiderReferences extends Command
{
    public function handle(RiderProfileService $riderProfileService): int
    {
        $updated = 0;
        $skipped = 0;
        $missingZone = 0;

        RiderProfile::query()
            ->select(['id', 'user_id', 'rider_ref', 'home_zone_id'])
            ->with('homeZone:id,code')
            ->chunkById(200, function ($riderProfiles) use ($riderProfileService, &$updated, &$skipped, &$missingZone): void {
                foreach ($riderProfiles as $riderProfile) {
                    if (preg_match('/^SWFT[A-Z]+\d{4,}$/', (string) $riderProfile->rider_ref) === 1) {
                        $skipped++;

                        continue;
                    }

                    if (blank($riderProfile->homeZone?->code)) {
                        $missingZone++;

                        continue;
                    }

                    $riderProfile->update([
                        'rider_ref' => $riderProfileService->generateRiderRef($riderProfile->user_id, $riderProfile->homeZone->code),
                    ]);

                    $updated++;
                }
            });

        $this->info("Updated {$updated} rider reference(s); {$skipped} already in the new format; {$missingZone} skipped for missing a home zone code.");

        return self::SUCCESS;
    }
}
