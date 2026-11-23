<?php

namespace App\Console\Commands;

use App\Jobs\ResolvePendingTransactionJob;
use App\Models\Transaction;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

#[Signature('transactions:resolve-pending')]
#[Description('Poll the gateway for still-pending withdrawal/reversal disbursements and resolve them')]
class ResolvePendingTransactions extends Command
{
    public function handle(): int
    {
        $count = 0;

        Transaction::query()
            ->where('status', 'pending')
            ->whereNotNull('gateway_reference')
            ->whereIn('transaction_type', ['withdrawal', 'reversal'])
            ->where(fn (Builder $query): Builder => $query
                ->whereNull('next_status_check_at')
                ->orWhere('next_status_check_at', '<=', now()))
            ->select('id')
            ->chunkById(100, function ($transactions) use (&$count): void {
                foreach ($transactions as $transaction) {
                    ResolvePendingTransactionJob::dispatch($transaction->id);
                    $count++;
                }
            });

        $this->info("Queued {$count} pending transaction(s) for resolution.");

        return self::SUCCESS;
    }
}
