<?php

namespace App\Models;

use Carbon\CarbonImmutable;

/**
 * @property int $id
 * @property string|null $name
 * @property string $email
 * @property string|null $phone
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class WaitlistEntry extends BaseModel
{
    protected $fillable = [
        'name',
        'email',
        'phone',
    ];
}
