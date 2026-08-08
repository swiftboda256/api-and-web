@php
    $pickup = $trip->pickup_location;
    $dropoff = $trip->dropoff_location;
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="lg:col-span-2">
        <div class="flex aspect-video w-full flex-col items-center justify-center gap-2 rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 text-center dark:border-white/10 dark:bg-gray-800/50">
            <x-heroicon-o-map class="h-10 w-10 text-gray-400 dark:text-gray-500" />
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Map view coming soon</p>
            <p class="max-w-xs text-xs text-gray-400 dark:text-gray-500">
                This is a placeholder. Once a map provider is configured, the pickup and dropoff route will render here.
            </p>
        </div>
    </div>

    <div class="space-y-4">
        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <div class="flex items-start gap-2">
                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"></span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Pickup</p>
                    <p class="text-sm text-gray-950 dark:text-white">{{ $trip->pickup_address ?? 'No address recorded' }}</p>
                    @if ($pickup)
                        <p class="mt-1 font-mono text-xs text-gray-400 dark:text-gray-500">
                            {{ number_format($pickup->getLatitude(), 6) }}, {{ number_format($pickup->getLongitude(), 6) }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="my-3 ml-[4px] h-4 w-px border-l-2 border-dashed border-gray-300 dark:border-white/10"></div>

            <div class="flex items-start gap-2">
                <span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full bg-rose-500"></span>
                <div>
                    <p class="text-xs font-medium text-gray-500 dark:text-gray-400">Dropoff</p>
                    <p class="text-sm text-gray-950 dark:text-white">{{ $trip->dropoff_address ?? 'No address recorded' }}</p>
                    @if ($dropoff)
                        <p class="mt-1 font-mono text-xs text-gray-400 dark:text-gray-500">
                            {{ number_format($dropoff->getLatitude(), 6) }}, {{ number_format($dropoff->getLongitude(), 6) }}
                        </p>
                    @endif
                </div>
            </div>
        </div>

        <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
            <dl class="space-y-3 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Distance</dt>
                    <dd class="text-gray-950 dark:text-white">{{ number_format((float) $trip->distance_km, 1) }} km</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Duration</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $trip->duration_minutes }} min</dd>
                </div>
            </dl>
        </div>
    </div>
</div>
