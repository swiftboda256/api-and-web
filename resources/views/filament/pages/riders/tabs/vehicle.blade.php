@php
    $vehicle = $rider->riderProfile->vehicle;
@endphp

@if (! $vehicle)
    <p class="text-sm text-gray-500 dark:text-gray-400">This rider hasn't added a vehicle yet.</p>
@else
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            @include('filament.pages.riders.partials.status-badge', ['status' => $vehicle->status])

            <div class="flex gap-2">
                @if ($vehicle->status !== 'approved')
                    <button
                        type="button"
                        wire:click="approveVehicle"
                        wire:confirm="Approve this vehicle?"
                        class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-emerald-500"
                    >
                        Approve vehicle
                    </button>
                @endif

                @if ($vehicle->status !== 'rejected')
                    <button
                        type="button"
                        wire:click="rejectVehicle"
                        class="rounded-lg bg-rose-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-rose-500"
                    >
                        Reject vehicle
                    </button>
                @endif
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Type</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $vehicle->vehicleType?->name ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Plate number</dt>
                <dd class="mt-0.5 font-mono text-gray-950 dark:text-white">{{ $vehicle->plate_number }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Registration number</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $vehicle->registration_number ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Make</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $vehicle->make ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Year</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $vehicle->year ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Color</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $vehicle->color ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-gray-500 dark:text-gray-400">Insurance expiry</dt>
                <dd class="mt-0.5 text-gray-950 dark:text-white">{{ $vehicle->insurance_expiry_at?->format('d M Y') ?? '—' }}</dd>
            </div>
        </dl>
    </div>
@endif
