<?php

namespace App\Services\ServiceCatalog;

use App\Models\ServiceCatalog;
use Illuminate\Database\Eloquent\Collection;

readonly class ServiceCatalogService
{
    /**
     * @return Collection<int, ServiceCatalog>
     */
    public function list(): Collection
    {
        return ServiceCatalog::query()
            ->where('is_active', true)
            ->with(['vehicleTypes' => fn ($query) => $query->where('is_active', true)->orderBy('name')])
            ->orderBy('name')
            ->get();
    }
}
