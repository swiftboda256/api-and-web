<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAccountDeletionJob;
use App\Models\UserDeleteRequest;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('accounts:process-due-deletions')]
#[Description('Dispatch processing jobs for account deletion requests whose 30-day grace period has elapsed (also retries requests left failed)')]
class ProcessDueAccountDeletions extends Command
{
    public function handle(): int
    {
        $count = 0;

        UserDeleteRequest::query()
            ->whereIn('status', ['pending', 'failed'])
            ->where('scheduled_for', '<=', now())
            ->select('id')
            ->chunkById(100, function ($deleteRequests) use (&$count): void {
                foreach ($deleteRequests as $deleteRequest) {
                    ProcessAccountDeletionJob::dispatch($deleteRequest->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} account deletion request(s) for processing.");

        return self::SUCCESS;
    }
}
