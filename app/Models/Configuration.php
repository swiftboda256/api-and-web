<?php

namespace App\Models;

use Illuminate\Support\Facades\Cache;

class Configuration extends BaseModel
{
    protected $fillable = [
        'key',
        'value',
        'group',
        'description',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
            'is_public' => 'boolean',
        ];
    }

    /**
     * Read a configuration value by key, cached briefly so hot paths
     * (e.g. OTP checks) don't hit the database on every call.
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        return Cache::remember("configuration:{$key}", 60, function () use ($key, $default) {
            $configuration = self::query()->where('key', $key)->first();

            if ($configuration === null) {
                return $default;
            }

            return $configuration->value ?? $default;
        });
    }
}
