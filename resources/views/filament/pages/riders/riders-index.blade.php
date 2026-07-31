<x-filament-panels::page>
    <div class="space-y-4">
        <div class="max-w-sm">
            <label for="rider-search" class="sr-only">Search riders</label>
            <div class="relative">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                    <x-heroicon-o-magnifying-glass class="h-4 w-4 text-gray-400" />
                </div>
                <input
                    type="search"
                    id="rider-search"
                    wire:model.live.debounce.400ms="search"
                    placeholder="Search by name or rider ref..."
                    class="block w-full rounded-lg border-gray-300 bg-white py-1.5 pl-9 text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-white/5 dark:text-white"
                />
            </div>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 font-medium">Rider</th>
                            <th class="px-4 py-3 font-medium">Rider ref</th>
                            <th class="px-4 py-3 font-medium">KYC status</th>
                            <th class="px-4 py-3 font-medium">Rating</th>
                            <th class="px-4 py-3 font-medium">Total trips</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-white/10">
                        @forelse ($this->riders as $rider)
                            <tr wire:key="rider-{{ $rider->id }}" class="hover:bg-gray-50 dark:hover:bg-white/5">
                                <td class="px-4 py-3">
                                    <a href="{{ \App\Filament\Pages\Riders\RiderDetails::getUrl(['record' => $rider->id]) }}" class="flex items-center gap-3">
                                        @include('filament.pages.riders.partials.avatar', ['user' => $rider, 'size' => 'md'])
                                        <span class="font-medium text-gray-950 dark:text-white">
                                            {{ $rider->name !== '' ? $rider->name : 'Unnamed rider' }}
                                        </span>
                                    </a>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-600 dark:text-gray-400">
                                    {{ $rider->riderProfile?->rider_ref ?? '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @include('filament.pages.riders.partials.status-badge', ['status' => $rider->riderProfile?->kyc_status])
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    ⭐ {{ number_format((float) $rider->rating_avg, 2) }}
                                    <span class="text-xs text-gray-400">({{ $rider->rating_count }})</span>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300">
                                    {{ number_format($rider->riderProfile?->total_trips ?? 0) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a
                                        href="{{ \App\Filament\Pages\Riders\RiderDetails::getUrl(['record' => $rider->id]) }}"
                                        class="text-sm font-medium text-primary-600 hover:text-primary-500"
                                    >
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">
                                    No riders found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($this->riders->hasPages())
                <div class="border-t border-gray-200 px-4 py-3 dark:border-white/10">
                    {{ $this->riders->links() }}
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
