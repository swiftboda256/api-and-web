<?php

namespace App\Console\Commands;

use App\Jobs\ResolvePendingYoTransactionJob;
use App\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('payments:resolve-pending-yo-transactions')]
#[Description('Poll Yo! Payments for the status of transactions pending more than 10 seconds')]
class ResolvePendingYoTransactions extends Command
{
    public function handle(): int
    {
        $count = 0;

        Transaction::query()
            ->where('gateway', 'yo')
            ->where('status', 'pending')
            ->whereNotNull('gateway_reference')
            ->where('created_at', '<=', now()->subSeconds(10))
            ->select('id')
            ->chunkById(100, function ($transactions) use (&$count): void {
                foreach ($transactions as $transaction) {
                    ResolvePendingYoTransactionJob::dispatch($transaction->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} pending Yo! transaction(s) for status resolution.");

        return self::SUCCESS;
    }
}
