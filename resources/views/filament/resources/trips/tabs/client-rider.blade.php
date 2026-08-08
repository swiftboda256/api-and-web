@php
    $customer = $trip->customer;
    $rider = $trip->rider;
    $riderProfile = $rider?->riderProfile;
    $vehicle = $riderProfile?->vehicle;
@endphp

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <div class="rounded-lg border border-gray-200 p-6 dark:border-white/10">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Client</h3>

        @if ($customer)
            <div class="mt-4 flex items-center gap-3">
                @include('filament.pages.riders.partials.avatar', ['user' => $customer, 'size' => 'md'])

                <div>
                    <p class="font-medium text-gray-950 dark:text-white">{{ $customer->name !== '' ? $customer->name : 'Unnamed customer' }}</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        @include('filament.pages.riders.partials.status-badge', ['status' => $customer->status])
                    </div>
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                    <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $customer->phone }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                    <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $customer->email ?? '—' }}</dd>
                </div>
            </dl>
        @else
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No customer on this trip.</p>
        @endif
    </div>

    <div class="rounded-lg border border-gray-200 p-6 dark:border-white/10">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Rider</h3>

            @if ($rider)
                <a
                    href="{{ \App\Filament\Pages\Riders\RiderDetails::getUrl(['record' => $rider->id]) }}"
                    class="text-xs font-medium text-primary-600 hover:underline dark:text-primary-400"
                >
                    View full profile
                </a>
            @endif
        </div>

        @if ($rider)
            <div class="mt-4 flex items-center gap-3">
                @include('filament.pages.riders.partials.avatar', ['user' => $rider, 'size' => 'md'])

                <div>
                    <p class="font-medium text-gray-950 dark:text-white">{{ $rider->name !== '' ? $rider->name : 'Unnamed rider' }}</p>
                    <div class="mt-1 flex flex-wrap gap-1.5">
                        @include('filament.pages.riders.partials.status-badge', ['status' => $rider->status])
                        @if ($riderProfile)
                            @include('filament.pages.riders.partials.status-badge', ['status' => $riderProfile->kyc_status])
                        @endif
                    </div>
                </div>
            </div>

            <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Phone</dt>
                    <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->phone }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Email</dt>
                    <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $rider->email ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Rating</dt>
                    <dd class="mt-0.5 text-gray-950 dark:text-white">⭐ {{ number_format((float) $rider->rating_avg, 2) }} ({{ $rider->rating_count }})</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Vehicle</dt>
                    <dd class="mt-0.5 text-gray-950 dark:text-white">
                        {{ $vehicle ? "{$vehicle->vehicleType?->name} · {$vehicle->plate_number}" : '—' }}
                    </dd>
                </div>
            </dl>
        @else
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No rider has been assigned to this trip yet.</p>
        @endif
    </div>
</div>
