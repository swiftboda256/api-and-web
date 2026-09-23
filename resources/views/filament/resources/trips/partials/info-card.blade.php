@php
    $isCancelled = $trip->status === 'cancelled';
    $isShared = in_array($trip->type, ['ride_share', 'delivery_share'], true);
    $isDelivery = in_array($trip->type, ['delivery', 'delivery_share'], true);
    $primaryItem = ($isDelivery ? $trip->deliveries : $trip->passengers)->first();
    // Ride/ride_share's fare/payment data lives on the passenger's fare breakdown now;
    // delivery keeps it on the delivery record directly (not moved yet).
    $breakdown = $isDelivery ? null : $primaryItem?->fareBreakdown;
    $currencyCode = $isDelivery ? $primaryItem?->currency_code : $breakdown?->currency_code;
    $finalFare = $isDelivery ? $primaryItem?->final_fare : $breakdown?->final_fare;
    $paymentMethod = $isDelivery ? $primaryItem?->payment_method : $breakdown?->payment_method;
    $paymentStatus = $isDelivery ? $primaryItem?->payment_status : $breakdown?->payment_status;
@endphp

<div class="rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900">
    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="font-mono text-xs text-gray-500 dark:text-gray-400">{{ $trip->trip_number }}</p>
            <h2 class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">
                @if ($isShared)
                    Shared trip &middot; {{ $manifestCount }} {{ in_array($trip->type, ['delivery', 'delivery_share'], true) ? 'deliveries' : 'passengers' }}
                @else
                    {{ $trip->pickup_address ?? 'Pickup' }} &rarr; {{ $trip->dropoff_address ?? 'Dropoff' }}
                @endif
            </h2>

            <div class="mt-2 flex flex-wrap gap-1.5">
                @include('filament.pages.riders.partials.status-badge', ['status' => $trip->status])
                @include('filament.pages.riders.partials.status-badge', ['status' => $trip->type])
                @if (! $isShared)
                    @include('filament.pages.riders.partials.status-badge', ['status' => $paymentStatus])
                @endif
            </div>

            @if ($isShared)
                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    See the Manifest tab for each {{ in_array($trip->type, ['delivery', 'delivery_share'], true) ? 'delivery' : 'passenger' }}'s own pickup, dropoff, status and payment.
                </p>
            @endif
        </div>

        <div class="flex gap-6 text-center">
            @if (! $isShared)
                <div>
                    <p class="text-xl font-semibold text-gray-950 dark:text-white">{{ number_format((float) $primaryItem?->distance_km, 1) }} km</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Distance</p>
                </div>
                <div>
                    <p class="text-xl font-semibold text-gray-950 dark:text-white">{{ $primaryItem?->duration_minutes }} min</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Duration</p>
                </div>
            @endif
            <div>
                <p class="text-xl font-semibold text-gray-950 dark:text-white">
                    {{ $currencyCode }} {{ number_format($manifestTotalFare, 0) }}
                </p>
                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $isShared ? 'Total across all riders' : ($finalFare !== null ? 'Final fare' : 'Estimated fare') }}</p>
            </div>
        </div>
    </div>

    <dl class="mt-6 grid grid-cols-2 gap-x-6 gap-y-4 border-t border-gray-100 pt-6 text-sm sm:grid-cols-3 lg:grid-cols-4 dark:border-white/10">
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Zone</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $trip->zone?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Vehicle type</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $trip->vehicleType?->name ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Payment method</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $paymentMethod ? str($paymentMethod)->headline() : '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Promo code</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $primaryItem?->promoCode?->code ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Requested at</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $trip->requested_at?->format('d M Y, H:i') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-gray-500 dark:text-gray-400">Accepted at</dt>
            <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $trip->accepted_at?->format('d M Y, H:i') ?? '—' }}</dd>
        </div>

        @if ($isCancelled)
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Cancelled at</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $trip->cancelled_at?->format('d M Y, H:i') ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Cancellation reason</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $trip->cancellationReason?->label ?? '—' }}</dd>
            </div>
        @else
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Completed at</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $trip->completed_at?->format('d M Y, H:i') ?? '—' }}</dd>
            </div>
        @endif
    </dl>
</div>
