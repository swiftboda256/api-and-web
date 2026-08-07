<?php

namespace App\Services\SupportCategory;

use App\Models\SupportCategory;
use Illuminate\Database\Eloquent\Collection;

readonly class SupportCategoryService
{
    /**
     * @return Collection<int, SupportCategory>
     */
    public function list(): Collection
    {
        return SupportCategory::query()->where('is_active', true)->orderBy('name')->get();
    }
}
