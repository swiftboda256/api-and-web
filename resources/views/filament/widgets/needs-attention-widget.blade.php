@php
    $tones = [
        'danger' => 'bg-rose-50 text-rose-600 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-400 dark:ring-rose-400/20',
        'warning' => 'bg-amber-50 text-amber-600 ring-amber-600/20 dark:bg-amber-500/10 dark:text-amber-400 dark:ring-amber-400/20',
        'info' => 'bg-sky-50 text-sky-600 ring-sky-600/20 dark:bg-sky-500/10 dark:text-sky-400 dark:ring-sky-400/20',
        'clear' => 'bg-emerald-50 text-emerald-600 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-400 dark:ring-emerald-400/20',
    ];
@endphp

<x-filament-widgets::widget wire:poll.60s>
    <x-filament::section
        heading="Needs attention"
        :description="$allClear ? 'All clear — nothing waiting on an admin.' : 'Queues waiting on an admin'"
        icon="heroicon-o-bell-alert"
    >
        <ul class="-my-2 divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($items as $item)
                @php
                    $tone = $item['count'] === 0 && $item['tone'] !== 'info' ? 'clear' : $item['tone'];
                    $tag = $item['url'] ? 'a' : 'div';
                @endphp
                <li>
                    <{{ $tag }}
                        @if ($item['url']) href="{{ $item['url'] }}" @endif
                        @class([
                            'group -mx-2 flex items-center gap-3 rounded-lg px-2 py-2.5',
                            'transition hover:bg-gray-50 dark:hover:bg-white/5' => $item['url'],
                        ])
                    >
                        <span @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ring-1 ring-inset', $tones[$tone]])>
                            <x-filament::icon :icon="$tone === 'clear' ? 'heroicon-o-check' : $item['icon']" class="h-5 w-5" />
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</span>
                            @if ($item['hint'] ?? null)
                                <span class="block text-xs text-rose-600 dark:text-rose-400">{{ $item['hint'] }}</span>
                            @elseif (! $item['url'] && $tone !== 'clear')
                                <span class="block text-xs text-gray-500 dark:text-gray-400">No admin page yet</span>
                            @endif
                        </span>

                        <span @class(['rounded-full px-2.5 py-0.5 text-sm font-semibold tabular-nums ring-1 ring-inset', $tones[$tone]])>
                            {{ number_format($item['count']) }}
                        </span>

                        @if ($item['url'])
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400 transition group-hover:translate-x-0.5" />
                        @endif
                    </{{ $tag }}>
                </li>
            @endforeach
        </ul>
    </x-filament::section>
</x-filament-widgets::widget>
