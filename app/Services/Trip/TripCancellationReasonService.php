<?php

namespace App\Services\Trip;

use App\Models\TripCancellationReason;
use Illuminate\Database\Eloquent\Collection;

readonly class TripCancellationReasonService
{
    /**
     * @return Collection<int, TripCancellationReason>
     */
    public function list(string $appliesTo): Collection
    {
        return TripCancellationReason::query()
            ->where('is_active', true)
            ->whereIn('applies_to', [$appliesTo, 'both'])
            ->orderBy('label')
            ->get();
    }
}
