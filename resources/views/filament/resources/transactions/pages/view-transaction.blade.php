<x-filament-panels::page>
    @php
        $transaction = $this->transaction;
        $currency = $transaction->currency_code;
        $money = fn ($amount) => $amount === null ? '—' : $currency.' '.number_format((float) $amount, 2);
        $dateTime = fn ($date) => $date?->format('d M Y, H:i:s') ?? '—';
        $isCredit = $transaction->direction === 'credit';

        $statusIcon = match ($transaction->status) {
            'completed' => \Filament\Support\Icons\Heroicon::OutlinedCheckCircle,
            'pending' => \Filament\Support\Icons\Heroicon::OutlinedClock,
            'failed' => \Filament\Support\Icons\Heroicon::OutlinedXCircle,
            'reversed' => \Filament\Support\Icons\Heroicon::OutlinedArrowUturnLeft,
            default => null,
        };

        $directionIcon = match ($transaction->direction) {
            'credit' => \Filament\Support\Icons\Heroicon::OutlinedArrowDownLeft,
            'debit' => \Filament\Support\Icons\Heroicon::OutlinedArrowUpRight,
            default => null,
        };
        $methodIcon = match ($transaction->method) {
            'wallet' => \Filament\Support\Icons\Heroicon::OutlinedWallet,
            'cash' => \Filament\Support\Icons\Heroicon::OutlinedBanknotes,
            'mobile_money' => \Filament\Support\Icons\Heroicon::OutlinedDevicePhoneMobile,
            'card' => \Filament\Support\Icons\Heroicon::OutlinedCreditCard,
            default => null,
        };
        $typeIcon = match ($transaction->transaction_type) {
            'topup' => \Filament\Support\Icons\Heroicon::OutlinedArrowDownTray,
            'trip_payment' => \Filament\Support\Icons\Heroicon::OutlinedMapPin,
            'trip_payout' => \Filament\Support\Icons\Heroicon::OutlinedBanknotes,
            'refund' => \Filament\Support\Icons\Heroicon::OutlinedArrowUturnLeft,
            'withdrawal' => \Filament\Support\Icons\Heroicon::OutlinedArrowUpTray,
            'withdrawal_charge', 'commission' => \Filament\Support\Icons\Heroicon::OutlinedReceiptPercent,
            'promo_credit' => \Filament\Support\Icons\Heroicon::OutlinedGift,
            'adjustment' => \Filament\Support\Icons\Heroicon::OutlinedAdjustmentsHorizontal,
            default => null,
        };

        // Renders the shared status badge as a value in the details grid.
        $badge = fn (?string $value, $icon) => $value === null
            ? '—'
            : new \Illuminate\Support\HtmlString(view('filament.pages.riders.partials.status-badge', ['status' => $value, 'icon' => $icon])->render());

        // label => [icon, value]
        $sections = [
            'Transaction' => [
                'ID' => [\Filament\Support\Icons\Heroicon::OutlinedHashtag, $transaction->id],
                'Type' => [\Filament\Support\Icons\Heroicon::OutlinedTag, $badge($transaction->transaction_type, $typeIcon)],
                'Method' => [\Filament\Support\Icons\Heroicon::OutlinedCreditCard, $badge($transaction->method, $methodIcon)],
                'Direction' => [\Filament\Support\Icons\Heroicon::OutlinedArrowsRightLeft, $badge($transaction->direction, $directionIcon)],
                'Amount' => [\Filament\Support\Icons\Heroicon::OutlinedBanknotes, $money($transaction->amount)],
                'Currency' => [\Filament\Support\Icons\Heroicon::OutlinedCurrencyDollar, $currency],
                'Narration' => [\Filament\Support\Icons\Heroicon::OutlinedChatBubbleBottomCenterText, $transaction->narration ?? '—'],
                'Failure reason' => [\Filament\Support\Icons\Heroicon::OutlinedExclamationTriangle, $transaction->failure_reason ?? '—'],
            ],
            'Gateway' => [
                'Gateway' => [\Filament\Support\Icons\Heroicon::OutlinedBuildingLibrary, $transaction->gateway ? str($transaction->gateway)->headline() : '—'],
                'Gateway reference' => [\Filament\Support\Icons\Heroicon::OutlinedLink, $transaction->gateway_reference ?? '—'],
                'External reference' => [\Filament\Support\Icons\Heroicon::OutlinedArrowTopRightOnSquare, $transaction->external_reference ?? '—'],
                'Network reference' => [\Filament\Support\Icons\Heroicon::OutlinedSignal, $transaction->network_reference ?? '—'],
                'Phone' => [\Filament\Support\Icons\Heroicon::OutlinedDevicePhoneMobile, $transaction->phone ?? '—'],
            ],
            'Wallet' => [
                'Wallet ID' => [\Filament\Support\Icons\Heroicon::OutlinedWallet, $transaction->wallet_id ?? '—'],
                'Balance before' => [\Filament\Support\Icons\Heroicon::OutlinedArrowLeftCircle, $money($transaction->balance_before)],
                'Balance after' => [\Filament\Support\Icons\Heroicon::OutlinedArrowRightCircle, $money($transaction->balance_after)],
                'Current balance' => [\Filament\Support\Icons\Heroicon::OutlinedScale, $transaction->wallet ? $money($transaction->wallet->balance) : '—'],
                'Updated At' => [\Filament\Support\Icons\Heroicon::OutlinedClock, $dateTime($transaction->wallet?->updated_at)],
            ],
            'Audit' => [
                'Created at' => [\Filament\Support\Icons\Heroicon::OutlinedCalendarDays, $dateTime($transaction->created_at)],
                'Created by' => [\Filament\Support\Icons\Heroicon::OutlinedUserPlus, $transaction->creator?->name ?? 'System'],
                'Updated at' => [\Filament\Support\Icons\Heroicon::OutlinedArrowPath, $dateTime($transaction->updated_at)],
                'Updated by' => [\Filament\Support\Icons\Heroicon::OutlinedPencilSquare, $transaction->updater?->name ?? '—'],
                'Deleted at' => [\Filament\Support\Icons\Heroicon::OutlinedTrash, $dateTime($transaction->deleted_at)],
                'Deleted by' => [\Filament\Support\Icons\Heroicon::OutlinedUserMinus, $transaction->deleter?->name ?? '—'],
            ],
        ];
    @endphp

    <div class="space-y-6">
        {{-- Summary --}}
        <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <p class="font-mono text-xs text-gray-500 dark:text-gray-400">#{{ $transaction->id }}</p>
                    <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                        {{ str($transaction->transaction_type)->headline() }}
                        <span class="font-normal text-gray-500 dark:text-gray-400">&middot; {{ $transaction->user?->name ?? 'Unknown user' }}</span>
                    </h2>

                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @include('filament.pages.riders.partials.status-badge', ['status' => $transaction->status, 'icon' => $statusIcon])
                        @include('filament.pages.riders.partials.status-badge', ['status' => $transaction->method, 'icon' => $methodIcon])
                        @include('filament.pages.riders.partials.status-badge', ['status' => $transaction->direction, 'icon' => $directionIcon])
                    </div>
                </div>

                <div class="text-left lg:text-right">
                    <p class="text-2xl font-semibold {{ $isCredit ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400' }}">
                        {{ $isCredit ? '+' : '−' }}{{ $money($transaction->amount) }}
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ $dateTime($transaction->created_at) }}</p>
                </div>
            </div>

            @if ($transaction->failure_reason)
                <div class="mt-6 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:bg-rose-500/10 dark:text-rose-400">
                    {{ $transaction->failure_reason }}
                </div>
            @endif
        </div>

        @php
            $tabs = [
                'transaction' => 'Transaction',
                'gateway' => 'Gateway',
                'wallet' => 'Wallet',
                'user' => 'User',
                'audit' => 'Audit',
            ];
            $activeTab = array_key_exists($tab, $tabs) ? $tab : 'transaction';
        @endphp

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-2 dark:border-white/10">
                <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Transaction detail tabs">
                    @foreach ($tabs as $key => $label)
                        <button
                            type="button"
                            wire:click="setTab('{{ $key }}')"
                            class="shrink-0 border-b-2 px-4 py-3 text-sm font-medium transition-colors
                                {{ $activeTab === $key
                                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                @if ($activeTab === 'user')
                    @if ($transaction->user)
                        <dl class="grid grid-cols-1 gap-x-6 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                            <div class="min-w-0">
                                <dt class="font-semibold text-gray-700 dark:text-gray-300">
                                    Name:
                                </dt>
                                <dd class="mt-1">
                                    <a href="{{ \App\Filament\Resources\Users\UserResource::getUrl('edit', ['record' => $transaction->user]) }}"
                                       class="text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $transaction->user->name }}
                                    </a>
                                </dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="font-semibold text-gray-700 dark:text-gray-300">
                                    Email:
                                </dt>
                                <dd class="mt-1 break-all text-gray-950 dark:text-white">{{ $transaction->user->email ?? '—' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="font-semibold text-gray-700 dark:text-gray-300">
                                    Phone:
                                </dt>
                                <dd class="mt-1 text-gray-950 dark:text-white">{{ $transaction->user->phone ?? '—' }}</dd>
                            </div>
                        </dl>
                    @else
                        <p class="text-sm text-gray-500 dark:text-gray-400">This user no longer exists.</p>
                    @endif
                @else
                    <dl class="grid grid-cols-1 gap-x-6 gap-y-5 text-sm sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($sections[$tabs[$activeTab]] as $label => [$icon, $value])
                            <div class="min-w-0">
                                <dt class="font-semibold text-gray-700 dark:text-gray-300">
                                    {{ $label }}:
                                </dt>
                                <dd class="mt-1 break-all text-gray-950 dark:text-white">{{ $value }}</dd>
                            </div>
                        @endforeach
                    </dl>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
