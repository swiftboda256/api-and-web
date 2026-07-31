@php
    $trips = $this->trips;

    $sortIcon = function (string $column) use ($tripsSort, $tripsSortDirection) {
        if ($tripsSort !== $column) {
            return null;
        }

        return $tripsSortDirection === 'asc' ? 'heroicon-o-chevron-up' : 'heroicon-o-chevron-down';
    };
@endphp

<div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="max-w-xs flex-1">
            <label for="trips-search" class="sr-only">Search trips</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                </div>
                <input
                    type="search"
                    id="trips-search"
                    wire:model.live.debounce.400ms="tripsSearch"
                    placeholder="Search by name, trip number or phone..."
                    class="block w-full rounded-lg border border-primary-500 bg-white py-1.5 pl-9 text-sm text-gray-950 shadow-sm focus:border-primary-600 focus:ring-primary-500 dark:border-primary-400 dark:bg-white/5 dark:text-white"
                />
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <select
                wire:model.live="tripsType"
                class="rounded-lg border-gray-300 bg-white py-1.5 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-white/5 dark:text-white"
            >
                <option value="">All types</option>
                <option value="ride">Ride</option>
                <option value="delivery">Delivery</option>
            </select>

            <select
                wire:model.live="tripsStatus"
                class="rounded-lg border-gray-300 bg-white py-1.5 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-white/5 dark:text-white"
            >
                <option value="">All statuses</option>
                @foreach (['requested', 'searching', 'accepted', 'arrived', 'in_progress', 'completed', 'cancelled'] as $status)
                    <option value="{{ $status }}">{{ str($status)->headline() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    @if ($trips->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No trips match these filters.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Trip</th>
                        <th class="py-2 pr-4 font-medium">Customer name</th>
                        <th class="py-2 pr-4 font-medium">Customer phone</th>
                        <th class="py-2 pr-4 font-medium">Type</th>
                        <th class="py-2 pr-4 font-medium">Status</th>
                        <th class="py-2 pr-4 font-medium">
                            <button type="button" wire:click="sortTrips('fare')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                Fare
                                @if ($sortIcon('fare'))
                                    <x-dynamic-component :component="$sortIcon('fare')" class="h-3.5 w-3.5" />
                                @endif
                            </button>
                        </th>
                        <th class="py-2 pr-4 font-medium">
                            <button type="button" wire:click="sortTrips('requested_at')" class="inline-flex items-center gap-1 hover:text-gray-700 dark:hover:text-gray-200">
                                Requested
                                @if ($sortIcon('requested_at'))
                                    <x-dynamic-component :component="$sortIcon('requested_at')" class="h-3.5 w-3.5" />
                                @endif
                            </button>
                        </th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($trips as $trip)
                        <tr wire:key="trip-{{ $trip->id }}">
                            <td class="py-2 pr-4 font-mono text-xs text-gray-600 dark:text-gray-400">{{ $trip->trip_number }}</td>
                            <td class="py-2 pr-4 text-gray-950 dark:text-white">
                                {{ $trip->customer && $trip->customer->name !== '' ? $trip->customer->name : '—' }}
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                {{ $trip->customer?->phone ?? '—' }}
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ str($trip->type)->headline() }}</td>
                            <td class="py-2 pr-4">
                                @include('filament.pages.riders.partials.status-badge', ['status' => $trip->status])
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">
                                {{ $trip->final_fare !== null ? number_format((float) $trip->final_fare, 0) : ($trip->estimated_fare !== null ? number_format((float) $trip->estimated_fare, 0) . ' (est.)' : '—') }}
                            </td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $trip->requested_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $trips->links() }}
        </div>
    @endif
</div>
