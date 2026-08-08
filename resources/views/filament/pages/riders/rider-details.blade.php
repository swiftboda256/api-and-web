<x-filament-panels::page>
    @php($rider = $this->rider)

    <div class="space-y-6">
        <a
            href="{{ \App\Filament\Pages\Riders\RidersIndex::getUrl() }}"
            class="inline-flex items-center gap-1.5 text-sm font-medium text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
        >
            <x-heroicon-o-arrow-left class="h-4 w-4" />
            Back to riders
        </a>

        @include('filament.pages.riders.partials.info-card', ['rider' => $rider])

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-2 dark:border-white/10">
                <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Rider detail tabs">
                    @foreach ([
                        'kyc' => 'KYC',
                        'vehicle' => 'Vehicles',
                        'trips' => 'Trips',
                        'ratings' => 'Ratings',
                        'transactions' => 'Transactions',
                        'security' => 'Security',
                        'account' => 'Account',
                    ] as $key => $label)
                        <button
                            type="button"
                            wire:click="setTab('{{ $key }}')"
                            class="shrink-0 border-b-2 px-4 py-3 text-sm font-medium transition-colors
                                {{ $tab === $key
                                    ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400'
                                    : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
                        >
                            {{ $label }}
                        </button>
                    @endforeach
                </nav>
            </div>

            <div class="p-6">
                @switch($tab)
                    @case('kyc')
                        @include('filament.pages.riders.tabs.kyc', ['rider' => $rider])
                        @break

                    @case('vehicle')
                        @include('filament.pages.riders.tabs.vehicle', ['rider' => $rider])
                        @break

                    @case('trips')
                        @include('filament.pages.riders.tabs.trips')
                        @break

                    @case('ratings')
                        @include('filament.pages.riders.tabs.ratings')
                        @break

                    @case('transactions')
                        @include('filament.pages.riders.tabs.transactions')
                        @break

                    @case('security')
                        @include('filament.pages.riders.tabs.security', ['rider' => $rider])
                        @break

                    @case('account')
                        @include('filament.pages.riders.tabs.account', ['rider' => $rider])
                        @break
                @endswitch
            </div>
        </div>
    </div>

    @include('filament.pages.riders.partials.reject-modal')
    @include('filament.pages.riders.partials.document-preview-modal')
</x-filament-panels::page>
