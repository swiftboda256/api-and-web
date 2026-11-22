<x-filament-widgets::widget>
    <div class="relative overflow-hidden rounded-xl bg-gradient-to-br from-amber-500 via-amber-500 to-orange-600 p-6 text-white shadow-sm ring-1 ring-amber-600/20">
        <div class="pointer-events-none absolute -right-10 -top-16 h-56 w-56 rounded-full bg-white/10"></div>
        <div class="pointer-events-none absolute -bottom-20 right-24 h-40 w-40 rounded-full bg-white/10"></div>

        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <p class="text-sm font-medium text-amber-50/90">{{ $date }}</p>
                <h2 class="mt-1 text-2xl font-semibold tracking-tight">
                    {{ $greeting }}{{ $name ? ', '.$name : '' }}
                </h2>
                @unless ($greetingOnly)
                    <p class="mt-1 text-sm text-amber-50/90">Here's what's happening across Swift Boda right now.</p>
                @endunless
            </div>

            @if (count($links))
                <div class="flex flex-wrap gap-2">
                    @foreach ($links as $link)
                        <a
                            href="{{ $link['url'] }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-white/15 px-3 py-1.5 text-sm font-medium text-white ring-1 ring-white/25 transition hover:bg-white/25"
                        >
                            @if ($link['icon'])
                                <x-filament::icon :icon="$link['icon']" class="h-4 w-4" />
                            @endif
                            {{ $link['label'] }}
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-widgets::widget>
