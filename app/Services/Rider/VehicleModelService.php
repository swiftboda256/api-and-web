<?php

namespace App\Services\Rider;

use App\Models\VehicleModel;
use Illuminate\Database\Eloquent\Collection;

readonly class VehicleModelService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, VehicleModel>
     */
    public function list(array $filters): Collection
    {
        return VehicleModel::query()
            ->where('is_active', true)
            ->when(
                $filters['search'] ?? null,
                fn ($query, string $search) => $query->where(function ($query) use ($search): void {
                    $query->where('name', 'ilike', "%{$search}%")
                        ->orWhere('make', 'ilike', "%{$search}%");
                })
            )
            ->when($filters['vehicle_type_id'] ?? null, fn ($query, $vehicleTypeId) => $query->where('vehicle_type_id', $vehicleTypeId))
            ->orderBy('make')
            ->orderBy('name')
            ->limit((int) ($filters['limit'] ?? 20))
            ->get();
    }
}
