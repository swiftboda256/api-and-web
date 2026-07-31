@php
    $transactions = $this->transactions;

    $sortIcon = function (string $column) use ($transactionsSort, $transactionsSortDirection) {
        if ($transactionsSort !== $column) {
            return null;
        }

        return $transactionsSortDirection === 'asc' ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down';
    };
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="max-w-xs flex-1">
            <label for="transactions-search" class="sr-only">Search transactions</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                </div>
                <input
                    type="search"
                    id="transactions-search"
                    wire:model.live.debounce.400ms="transactionsSearch"
                    placeholder="Search by reference..."
                    class="block w-full rounded-lg border border-primary-500 bg-white py-1.5 pl-9 text-sm text-gray-950 shadow-sm focus:border-primary-600 focus:ring-primary-500 dark:border-primary-400 dark:bg-white/5 dark:text-white"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select
                wire:model.live="transactionsType"
                class="rounded-lg border-gray-300 bg-white py-1.5 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-white/5 dark:text-white"
            >
                <option value="">All types</option>
                @foreach (['topup', 'trip_payment', 'trip_payout', 'refund', 'withdrawal', 'promo_credit', 'adjustment'] as $type)
                    <option value="{{ $type }}">{{ str($type)->headline() }}</option>
                @endforeach
            </select>

            <select
                wire:model.live="transactionsMethod"
                class="rounded-lg border-gray-300 bg-white py-1.5 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-white/5 dark:text-white"
            >
                <option value="">All methods</option>
                @foreach (['wallet', 'cash', 'mobile_money', 'card'] as $method)
                    <option value="{{ $method }}">{{ str($method)->headline() }}</option>
                @endforeach
            </select>

            <select
                wire:model.live="transactionsStatus"
                class="rounded-lg border-gray-300 bg-white py-1.5 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-white/5 dark:text-white"
            >
                <option value="">All statuses</option>
                @foreach (['pending', 'completed', 'failed', 'reversed'] as $status)
                    <option value="{{ $status }}">{{ str($status)->headline() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($transactions->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No transactions match these filters.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Reference</th>
                        <th class="py-2 pr-4 font-medium">Type</th>
                        <th class="py-2 pr-4 font-medium">Direction</th>
                        <th class="py-2 pr-4 font-medium">Amount</th>
                        <th class="py-2 pr-4 font-medium">Method</th>
                        <th class="py-2 pr-4 font-medium">Status</th>
                        <th class="py-2 pr-4 font-medium">
                            <button type="button" wire:click="sortTransactions('created_at')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                Date
                                @if ($sortIcon('created_at'))
                                    <x-dynamic-component :component="$sortIcon('created_at')" class="h-3.5 w-3.5" />
                                @endif
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($transactions as $transaction)
                        <tr wire:key="transaction-{{ $transaction->id }}">
                            <td class="py-2 pr-4">{{ $transaction->gateway_reference }}</td>
                            <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ str($transaction->transaction_type)->headline() }}</td>
                            <td class="py-2 pr-4">
                                <span class="{{ $transaction->direction === 'credit' ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                                    {{ $transaction->direction === 'credit' ? '+ Credit' : '− Debit' }}
                                </span>
                            </td>
                            <td class="py-2 pr-4 font-medium text-gray-950 dark:text-white">
                                {{ $transaction->currency_code }} {{ number_format((float) $transaction->amount, 0) }}
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ str($transaction->method)->headline() }}</td>
                            <td class="py-2 pr-4">
                                @include('filament.pages.riders.partials.status-badge', ['status' => $transaction->status])
                            </td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $transaction->created_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $transactions->links() }}
        </div>
    @endif
</div>
