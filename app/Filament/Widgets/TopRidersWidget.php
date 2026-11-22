<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\Riders\RiderDetails;
use App\Filament\Pages\Riders\RidersIndex;
use App\Models\Trip;
use App\Models\User;
use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/**
 * Riders with the most completed trips this month.
 */
class TopRidersWidget extends Widget
{
    use HasWidgetShield;

    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.top-riders-widget';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $since = now()->startOfMonth();

        /** @var array<int, int> $counts rider user id => completed trips */
        $counts = Cache::remember('dashboard:top-riders:'.$since->format('Y-m'), 60, fn (): array => Trip::query()
            ->where('status', 'completed')
            ->whereNotNull('rider_id')
            ->where('completed_at', '>=', $since)
            ->selectRaw('rider_id, COUNT(*) AS total')
            ->groupBy('rider_id')
            ->orderByDesc('total')
            ->limit(6)
            ->pluck('total', 'rider_id')
            ->map(fn ($total): int => (int) $total)
            ->all());

        $users = User::query()->whereKey(array_keys($counts))->get()->keyBy('id');
        $canOpen = RidersIndex::canAccess();

        $riders = collect($counts)
            ->filter(fn (int $total, int $id): bool => $users->has($id))
            ->map(function (int $total, int $id) use ($users, $canOpen): array {
                /** @var User $user */
                $user = $users->get($id);

                return [
                    'name' => $user->name,
                    'initials' => $user->initials() !== '' ? $user->initials() : '?',
                    'avatar' => $user->avatar_url,
                    'trips' => $total,
                    'rating' => $user->rating_count > 0 ? number_format((float) $user->rating_avg, 1) : null,
                    'url' => $canOpen ? RiderDetails::getUrl(['record' => $id]) : null,
                ];
            })
            ->values()
            ->all();

        return [
            'riders' => $riders,
            'month' => $since->format('F'),
            'max' => max([1, ...array_values($counts)]),
        ];
    }
}
