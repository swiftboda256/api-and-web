<?php

namespace App\Models;

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
}
