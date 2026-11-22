<x-filament-widgets::widget>
    <x-filament::section
        heading="Top riders"
        :description="'Most completed trips in '.$month"
        icon="heroicon-o-trophy"
    >
        @if (count($riders) === 0)
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <x-filament::icon icon="heroicon-o-truck" class="h-8 w-8 text-gray-300 dark:text-gray-600" />
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No completed trips yet this month.</p>
            </div>
        @else
            <ol class="-my-2 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($riders as $rider)
                    @php($tag = $rider['url'] ? 'a' : 'div')
                    <li>
                        <{{ $tag }}
                            @if ($rider['url']) href="{{ $rider['url'] }}" @endif
                            @class([
                                'group -mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5',
                                'transition hover:bg-gray-50 dark:hover:bg-white/5' => $rider['url'],
                            ])
                        >
                            <span @class([
                                'w-5 text-center text-sm font-semibold tabular-nums',
                                'text-amber-500' => $loop->first,
                                'text-gray-400 dark:text-gray-500' => ! $loop->first,
                            ])>{{ $loop->iteration }}</span>

                            @if ($rider['avatar'])
                                <img src="{{ $rider['avatar'] }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" />
                            @else
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 text-sm font-semibold text-amber-700 dark:bg-amber-500/15 dark:text-amber-400">
                                    {{ $rider['initials'] }}
                                </span>
                            @endif

                            <span class="min-w-0 flex-1">
                                <span class="flex items-center justify-between gap-2">
                                    <span class="truncate text-sm font-medium text-gray-950 dark:text-white">{{ $rider['name'] }}</span>
                                    <span class="shrink-0 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $rider['rating'] ? '⭐ '.$rider['rating'].' · ' : '' }}{{ number_format($rider['trips']) }} {{ str('trip')->plural($rider['trips']) }}
                                    </span>
                                </span>
                                <span class="mt-1.5 block h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                    <span class="block h-full rounded-full bg-amber-500" style="width: {{ round($rider['trips'] / $max * 100) }}%"></span>
                                </span>
                            </span>
                        </{{ $tag }}>
                    </li>
                @endforeach
            </ol>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
