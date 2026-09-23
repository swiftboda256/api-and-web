@php
    $isDelivery = in_array($trip->type, ['delivery', 'delivery_share'], true);
    $primaryItem = ($isDelivery ? $trip->deliveries : $trip->passengers)->first();
    // Delivery doesn't have a fare-breakdown record yet -- only ride/ride_share does.
    $breakdown = $isDelivery ? null : $primaryItem?->fareBreakdown;
@endphp

@if (! $breakdown)
    <div class="rounded-lg border border-dashed border-gray-300 p-8 text-center text-sm text-gray-500 dark:border-white/10 dark:text-gray-400">
        No itemised fare breakdown is recorded for this trip -- see the Manifest tab for each rider's own fare.
        <p class="mt-1">
            Total:
            <span class="font-medium text-gray-950 dark:text-white">{{ $primaryItem?->currency_code }} {{ number_format($manifestTotalFare, 2) }}</span>
        </p>
    </div>
@else
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-lg border border-gray-200 p-6 dark:border-white/10">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Fare breakdown</h3>

            <dl class="mt-4 space-y-2.5 text-sm">
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Base fare</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $breakdown->currency_code }} {{ number_format((float) $breakdown->base_fare, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Distance fare</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $breakdown->currency_code }} {{ number_format((float) $breakdown->distance_fare, 2) }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-gray-500 dark:text-gray-400">Time fare</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $breakdown->currency_code }} {{ number_format((float) $breakdown->time_fare, 2) }}</dd>
                </div>

                @if ((float) $breakdown->surge_multiplier > 1)
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Surge ({{ number_format((float) $breakdown->surge_multiplier, 2) }}&times;)</dt>
                        <dd class="text-gray-950 dark:text-white">+{{ $breakdown->currency_code }} {{ number_format((float) $breakdown->surge_amount, 2) }}</dd>
                    </div>
                @endif

                @if ((float) $breakdown->discount_amount > 0)
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">
                            Discount
                            @if ($primaryItem?->promoCode)
                                <span class="font-mono text-xs">({{ $primaryItem->promoCode->code }})</span>
                            @endif
                        </dt>
                        <dd class="text-emerald-600 dark:text-emerald-400">&minus;{{ $breakdown->currency_code }} {{ number_format((float) $breakdown->discount_amount, 2) }}</dd>
                    </div>
                @endif

                @if ((float) $breakdown->cancellation_fee > 0)
                    <div class="flex justify-between">
                        <dt class="text-gray-500 dark:text-gray-400">Cancellation fee</dt>
                        <dd class="text-gray-950 dark:text-white">+{{ $breakdown->currency_code }} {{ number_format((float) $breakdown->cancellation_fee, 2) }}</dd>
                    </div>
                @endif

                <div class="flex justify-between border-t border-gray-100 pt-2.5 text-base font-semibold dark:border-white/10">
                    <dt class="text-gray-950 dark:text-white">Total</dt>
                    <dd class="text-gray-950 dark:text-white">{{ $breakdown->currency_code }} {{ number_format((float) ($breakdown->total ?? $breakdown->final_fare ?? $breakdown->estimated_fare), 2) }}</dd>
                </div>
            </dl>
        </div>

        <div class="space-y-6">
            <div class="rounded-lg border border-gray-200 p-6 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Commission &amp; earnings</h3>

                @if ($breakdown->rider_earning === null)
                    <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">Not yet settled -- commission and rider earning are recorded on drop-off.</p>
                @else
                    <dl class="mt-4 space-y-2.5 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Commission rate</dt>
                            <dd class="text-gray-950 dark:text-white">{{ number_format((float) $breakdown->commission_rate, 1) }}%</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-gray-500 dark:text-gray-400">Commission amount</dt>
                            <dd class="text-gray-950 dark:text-white">{{ $breakdown->currency_code }} {{ number_format((float) $breakdown->commission_amount, 2) }}</dd>
                        </div>
                        <div class="flex justify-between border-t border-gray-100 pt-2.5 font-semibold dark:border-white/10">
                            <dt class="text-gray-950 dark:text-white">Rider earning</dt>
                            <dd class="text-gray-950 dark:text-white">{{ $breakdown->currency_code }} {{ number_format((float) $breakdown->rider_earning, 2) }}</dd>
                        </div>
                    </dl>
                @endif
            </div>

            <div class="rounded-lg border border-gray-200 p-6 dark:border-white/10">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Payment</h3>

                <div class="mt-4 flex flex-wrap gap-1.5">
                    @include('filament.pages.riders.partials.status-badge', ['status' => $breakdown->payment_status])
                    @if ($breakdown->payment_method)
                        @include('filament.pages.riders.partials.status-badge', ['status' => $breakdown->payment_method])
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
