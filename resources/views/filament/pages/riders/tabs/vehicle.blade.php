@php
    $vehicle = $rider->riderProfile->vehicle;
@endphp

@if (! $vehicle)
    <p class="text-sm text-gray-500 dark:text-gray-400">This rider hasn't added a vehicle yet.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                <tr>
                    <th class="py-2 pr-4 font-medium">Type</th>
                    <th class="py-2 pr-4 font-medium">Make</th>
                    <th class="py-2 pr-4 font-medium">Model</th>
                    <th class="py-2 pr-4 font-medium">Plate number</th>
                    <th class="py-2 pr-4 font-medium">Registration number</th>
                    <th class="py-2 pr-4 font-medium">Year</th>
                    <th class="py-2 pr-4 font-medium">Color</th>
                    <th class="py-2 pr-4 font-medium">Insurance expiry</th>
                    <th class="py-2 pr-4 font-medium">Status</th>
                    <th class="py-2 pr-4 font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                <tr>
                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $vehicle->vehicleType?->name ?? '—' }}</td>
                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $vehicle->vehicleModel?->make ?? '—' }}</td>
                    <td class="py-2 pr-4 text-gray-950 dark:text-white">{{ $vehicle->vehicleModel?->name ?? '—' }}</td>
                    <td class="py-2 pr-4 font-mono text-gray-950 dark:text-white">{{ $vehicle->plate_number }}</td>
                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $vehicle->registration_number ?? '—' }}</td>
                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $vehicle->year ?? '—' }}</td>
                    <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $vehicle->color ?? '—' }}</td>
                    <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $vehicle->insurance_expiry_at?->format('d M Y') ?? '—' }}</td>
                    <td class="py-2 pr-4">
                        @include('filament.pages.riders.partials.status-badge', ['status' => $vehicle->status])
                    </td>
                    <td class="py-2 pr-4">
                        <div
                            x-data="{
                                open: false,
                                top: 0,
                                left: 0,
                                toggle() {
                                    this.open = ! this.open;

                                    if (this.open) {
                                        const rect = $refs.actionsButton.getBoundingClientRect();
                                        this.top = rect.bottom + window.scrollY + 4;
                                        this.left = rect.right + window.scrollX - 160;
                                    }
                                },
                            }"
                            class="relative"
                        >
                            <button
                                x-ref="actionsButton"
                                type="button"
                                @click="toggle()"
                                class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-200 dark:hover:bg-white/5"
                            >
                                Actions
                                <x-heroicon-o-chevron-down class="h-3.5 w-3.5" />
                            </button>

                            <template x-teleport="body">
                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition
                                    @click.outside="open = false"
                                    :style="`top: ${top}px; left: ${left}px;`"
                                    class="fixed z-50 w-40 rounded-lg border border-gray-200 bg-white py-1 shadow-lg dark:border-white/10 dark:bg-gray-800"
                                >
                                    @if ($vehicle->status !== 'approved')
                                        <button
                                            type="button"
                                            wire:click="approveVehicle"
                                            @click="open = false"
                                            class="block w-full px-3 py-2 text-left text-sm text-emerald-700 hover:bg-emerald-50 dark:text-emerald-400 dark:hover:bg-emerald-500/10"
                                        >
                                            Approve
                                        </button>
                                    @endif

                                    <button
                                        type="button"
                                        wire:click="editVehicle"
                                        @click="open = false"
                                        class="block w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-50 dark:text-gray-200 dark:hover:bg-white/5"
                                    >
                                        Edit
                                    </button>

                                    @if ($vehicle->status !== 'rejected')
                                        <button
                                            type="button"
                                            wire:click="rejectVehicle"
                                            @click="open = false"
                                            class="block w-full px-3 py-2 text-left text-sm text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10"
                                        >
                                            Reject
                                        </button>
                                    @endif
                                </div>
                            </template>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
@endif
