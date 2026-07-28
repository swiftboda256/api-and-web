<?php

namespace App\Observers;

use App\Models\Rating;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RatingObserver
{
    public function saved(Rating $rating): void
    {
        $this->recalculate($rating->ratee_id);
    }

    public function deleted(Rating $rating): void
    {
        $this->recalculate($rating->ratee_id);
    }

    private function recalculate(int $userId): void
    {
        $stats = DB::table('ratings')
            ->where('ratee_id', $userId)
            ->whereNull('deleted_at')
            ->selectRaw('avg(score) as average, count(*) as total')
            ->first();

        User::query()->where('id', $userId)->update([
            'rating_avg' => round((float) ($stats->average ?? 0), 2),
            'rating_count' => (int) ($stats->total ?? 0),
        ]);
    }
}
