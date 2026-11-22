@php
    $isDelivery = in_array($trip->type, ['delivery', 'delivery_share'], true);
    $primaryItem = ($isDelivery ? $trip->deliveries : $trip->passengers)->first();
    $mapData = $this->mapData;
    $mapsApiKey = config('services.google.maps_js_api_key');
    $pickupPin = ['icon' => $isDelivery ? 'package' : 'person', 'color' => '#10b981'];
    $destinationPin = ['icon' => 'flag', 'color' => '#f43f5e'];
    $mapPins = [
        'start' => view('filament.resources.trips.partials.map-pin', [...$pickupPin, 'size' => 34])->render(),
        'destination' => view('filament.resources.trips.partials.map-pin', [...$destinationPin, 'size' => 34])->render(),
    ];
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        @if (empty($mapData['path']) && empty($mapData['stops']))
            <div class="flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 text-center dark:border-white/10 dark:bg-gray-800/50">
                <x-heroicon-o-map class="h-10 w-10 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No location data for this trip</p>
            </div>
        @elseif (blank($mapsApiKey))
            <div class="flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 text-center dark:border-white/10 dark:bg-gray-800/50">
                <x-heroicon-o-map class="h-10 w-10 text-gray-400 dark:text-gray-500" />
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Google Maps is not configured</p>
                <p class="max-w-xs text-xs text-gray-400 dark:text-gray-500">Set <code>GOOGLE_MAPS_JS_API_KEY</code> in the environment to show the trip path.</p>
            </div>
        @else
            <div
                wire:ignore
                wire:key="trip-map-{{ $trip->id }}"
                x-data="tripMap(@js($mapData), @js(['apiKey' => $mapsApiKey, 'mapId' => config('services.google.maps_map_id'), 'pins' => $mapPins]))"
                class="relative z-0 aspect-video w-full overflow-hidden rounded-lg border border-gray-200 dark:border-white/10"
            >
                <div x-ref="map" class="h-full w-full"></div>
                <div x-show="error" x-cloak class="absolute inset-0 flex items-center justify-center bg-gray-50 text-sm text-gray-500 dark:bg-gray-800 dark:text-gray-400" x-text="error"></div>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-x-5 gap-y-2 text-xs text-gray-500 dark:text-gray-400">
                <span class="flex items-center gap-1.5"><span class="h-0.5 w-5 rounded bg-amber-500"></span> Pickup → destination path ({{ count($mapData['path']) }} GPS points)</span>
                <span class="flex items-center gap-1.5">@include('filament.resources.trips.partials.map-pin', [...$pickupPin, 'size' => 12]) Pickup</span>
                @if (count($mapData['stops']) > 2)
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-violet-500"></span> Stops in between</span>
                @endif
                <span class="flex items-center gap-1.5">@include('filament.resources.trips.partials.map-pin', [...$destinationPin, 'size' => 12]) Destination</span>
                @if ($mapData['active'] && count($mapData['path']) > 0)
                    <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full border-2 border-white bg-sky-500 ring-1 ring-sky-500"></span> Rider last position</span>
                @endif
                @if (! $mapData['pickedUp'])
                    <span class="italic">Passenger not picked up yet — showing planned stops only</span>
                @elseif (count($mapData['path']) === 0)
                    <span class="italic">No GPS points recorded between pickup and dropoff</span>
                @endif
            </div>
        @endif
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Journey</p>

            @forelse ($mapData['stops'] as $index => $stop)
                @if ($index > 0)
                    <div class="my-1 ml-[9px] h-4 w-px border-l-2 border-dashed border-gray-300 dark:border-white/10"></div>
                @endif

                <div class="flex items-start gap-2">
                    <span class="flex w-5 shrink-0 justify-center">
                        @if ($stop['role'] === 'start')
                            @include('filament.resources.trips.partials.map-pin', [...$pickupPin, 'size' => 18])
                        @elseif ($stop['role'] === 'destination')
                            @include('filament.resources.trips.partials.map-pin', [...$destinationPin, 'size' => 18])
                        @else
                            <span class="mt-0.5 flex h-5 w-5 items-center justify-center rounded-full bg-violet-500 text-[10px] font-semibold text-white">{{ $index }}</span>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="text-xs font-medium text-gray-500 dark:text-gray-400">
                            {{ $stop['label'] }}@if (count($mapData['stops']) > 2) · {{ $stop['name'] }}@endif
                        </p>
                        <p class="text-sm text-gray-950 dark:text-white">{{ $stop['address'] ?? 'No address recorded' }}</p>
                        <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
                            {{ $stop['time'] ?? 'Not reached yet' }}
                            <span class="ml-1 font-mono">{{ number_format($stop['lat'], 6) }}, {{ number_format($stop['lng'], 6) }}</span>
                        </p>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-500 dark:text-gray-400">No stops recorded</p>
            @endforelse
        </div>

        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Distance</dt>
                    <dd class="text-gray-950 dark:text-white">{{ number_format((float) $primaryItem?->distance_km, 1) }} km</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Duration</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $primaryItem?->duration_minutes }} min</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
