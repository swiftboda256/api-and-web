@php
    $ratings = $this->ratings;
@endphp

<div class="space-y-4">
    @if ($ratings->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">No ratings have been left for this rider yet.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm">
                <thead class="border-b border-gray-200 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                    <tr>
                        <th class="py-2 pr-4 font-medium">Score</th>
                        <th class="py-2 pr-4 font-medium">Comment</th>
                        <th class="py-2 pr-4 font-medium">Tags</th>
                        <th class="py-2 pr-4 font-medium">Rated by</th>
                        <th class="py-2 pr-4 font-medium">Trip</th>
                        <th class="py-2 pr-4 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($ratings as $rating)
                        <tr wire:key="rating-{{ $rating->id }}">
                            <td class="py-2 pr-4 whitespace-nowrap text-amber-500">
                                {{ str_repeat('★', max(0, min(5, $rating->score))) }}{{ str_repeat('☆', max(0, 5 - $rating->score)) }}
                            </td>
                            <td class="py-2 pr-4 text-gray-700 dark:text-gray-300">{{ $rating->comment ?? '—' }}</td>
                            <td class="py-2 pr-4">
                                @if (filled($rating->tags))
                                    <div class="flex flex-wrap gap-1">
                                        @foreach ($rating->tags as $tag)
                                            @include('filament.pages.riders.partials.status-badge', ['status' => $tag])
                                        @endforeach
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="py-2 pr-4 text-gray-950 dark:text-white">
                                {{ $rating->rater?->name ?: '—' }}
                                <span class="text-xs text-gray-500 dark:text-gray-400">({{ str($rating->rater_role)->headline() }})</span>
                            </td>
                            <td class="py-2 pr-4 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $rating->trip?->trip_number ?? '—' }}</td>
                            <td class="py-2 pr-4 text-gray-500 dark:text-gray-400">{{ $rating->created_at?->format('d M Y H:i') ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $ratings->links() }}
        </div>
    @endif
</div>
