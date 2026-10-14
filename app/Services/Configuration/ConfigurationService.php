<?php

namespace App\Services\Configuration;

use App\Models\Configuration;
use Illuminate\Database\Eloquent\Collection;

readonly class ConfigurationService
{
    /**
     * The public configurations under one app's guard (user_app or rider_app).
     *
     * @return Collection<int, Configuration>
     */
    public function listPublic(string $guard): Collection
    {
        return Configuration::query()->where('guard', $guard)->where('is_public', true)->orderBy('key')->get();
    }
}
