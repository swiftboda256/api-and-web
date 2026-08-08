<x-filament-panels::page>
    @php($trip = $this->trip)

    <div class="space-y-6">
        @include('filament.resources.trips.partials.info-card', ['trip' => $trip])

        <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-2 dark:border-white/10">
                <nav class="-mb-px flex gap-1 overflow-x-auto" aria-label="Trip detail tabs">
                    @foreach ([
                        'map' => 'Map',
                        'cost-breakdown' => 'Cost breakdown',
                        'client-rider' => 'Client & Rider',
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
                    @case('map')
                        @include('filament.resources.trips.tabs.map', ['trip' => $trip])
                        @break

                    @case('cost-breakdown')
                        @include('filament.resources.trips.tabs.cost-breakdown', ['trip' => $trip])
                        @break

                    @case('client-rider')
                        @include('filament.resources.trips.tabs.client-rider', ['trip' => $trip])
                        @break
                @endswitch
            </div>
        </div>
    </div>
</x-filament-panels::page>
