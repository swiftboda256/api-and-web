<?php

namespace App\Services\Rider;

use App\Models\VehicleType;
use Illuminate\Database\Eloquent\Collection;

readonly class VehicleTypeService
{
    /**
     * @return Collection<int, VehicleType>
     */
    public function list(): Collection
    {
        return VehicleType::query()->where('is_active', true)->orderBy('name')->get();
    }
}
