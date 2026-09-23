@php
    $isDelivery = in_array($trip->type, ['delivery', 'delivery_share'], true);
    $items = $isDelivery ? $trip->deliveries : $trip->passengers;
@endphp

<div class="space-y-4">
    @if ($items->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
            No {{ $isDelivery ? 'deliveries' : 'passengers' }} recorded on this trip.
        </div>
    @else
        @foreach ($items as $item)
            @php
                $person = $isDelivery ? $item->sender : $item->customer;
                $pickupStop = $item->stops->firstWhere('stop_type', 'pickup');
                $dropoffStop = $item->stops->firstWhere('stop_type', 'dropoff');
                // Ride/ride_share's fare/payment data lives on the passenger's fare
                // breakdown now; delivery keeps it on the delivery record directly.
                $itemBreakdown = $isDelivery ? null : $item->fareBreakdown;
                $itemCurrencyCode = $isDelivery ? $item->currency_code : $itemBreakdown?->currency_code;
                $itemFinalFare = $isDelivery ? $item->final_fare : $itemBreakdown?->final_fare;
                $itemEstimatedFare = $isDelivery ? $item->estimated_fare : $itemBreakdown?->estimated_fare;
                $itemPaymentStatus = $isDelivery ? $item->payment_status : $itemBreakdown?->payment_status;
                $itemDiscountPercentage = $isDelivery ? $item->discount_percentage : $itemBreakdown?->discount_percentage;
            @endphp

            <div class="rounded-lg border border-gray-200 p-4 dark:border-white/10">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div class="flex items-start gap-3">
                        @if ($person)
                            @include('filament.pages.riders.partials.avatar', ['user' => $person, 'size' => 'md'])
                        @endif

                        <div>
                            <p class="font-medium text-gray-950 dark:text-white">
                                {{ $person && $person->name !== '' ? $person->name : 'Unknown '.($isDelivery ? 'sender' : 'customer') }}
                            </p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $person?->phone ?? '—' }}</p>

                            @if ($isDelivery)
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    To {{ $item->recipient_name }} ({{ $item->recipient_phone }})
                                </p>
                            @endif

                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @include('filament.pages.riders.partials.status-badge', ['status' => $item->status])
                                @include('filament.pages.riders.partials.status-badge', ['status' => $itemPaymentStatus])
                            </div>
                        </div>
                    </div>

                    <div class="text-right">
                        <p class="text-lg font-semibold text-gray-950 dark:text-white">
                            {{ $itemCurrencyCode }} {{ number_format((float) ($itemFinalFare ?? $itemEstimatedFare), 0) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $itemFinalFare !== null ? 'Final fare' : 'Estimated fare' }}
                            @if ($itemDiscountPercentage)
                                &middot; {{ number_format((float) $itemDiscountPercentage, 0) }}% off
                            @endif
                        </p>
                    </div>
                </div>

                <dl class="mt-4 grid grid-cols-1 gap-x-4 gap-y-3 border-t border-gray-100 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-4 dark:border-white/10">
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Pickup</dt>
                        <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $pickupStop?->address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Dropoff</dt>
                        <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $dropoffStop?->address ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">Distance</dt>
                        <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $item->distance_km !== null ? number_format((float) $item->distance_km, 1).' km' : '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-gray-500 dark:text-gray-400">
                            {{ $isDelivery ? 'Package' : 'Seats' }}
                        </dt>
                        <dd class="mt-0.5 text-gray-950 dark:text-white">
                            @if ($isDelivery)
                                {{ str($item->package_size ?? 'small')->headline() }}
                                @if ($item->package_weight_kg)
                                    &middot; {{ number_format((float) $item->package_weight_kg, 1) }} kg
                                @endif
                            @else
                                {{ $item->seats_requested }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($item->status === 'cancelled')
                    <p class="mt-3 text-xs text-rose-600 dark:text-rose-400">
                        Cancelled {{ $item->cancelled_at?->format('d M Y, H:i') }}
                        @if ($item->cancellationReason)
                            &middot; {{ $item->cancellationReason->label }}
                        @endif
                    </p>
                @endif
            </div>
        @endforeach
    @endif
</div>
