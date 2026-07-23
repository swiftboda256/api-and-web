<?php

namespace App\Models;

use App\Models\Concerns\HasAuditColumns;
use Illuminate\Database\Eloquent\Model;

abstract class BaseModel extends Model
{
    use HasAuditColumns;
}
