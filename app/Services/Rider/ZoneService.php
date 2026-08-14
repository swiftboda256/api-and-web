<?php

namespace App\Services\Rider;

use App\Models\Zone;
use Illuminate\Database\Eloquent\Collection;

readonly class ZoneService
{
    /**
     * @return Collection<int, Zone>
     */
    public function list(): Collection
    {
        return Zone::query()->where('is_active', true)->orderBy('name')->get();
    }
}
