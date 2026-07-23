<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;

/**
 * Stamps created_by/updated_by/deleted_by from the authenticated user and enables soft deletes.
 */
trait HasAuditColumns
{
    use SoftDeletes;

    protected static function bootHasAuditColumns(): void
    {
        static::creating(function (Model $model): void {
            if (Auth::check() && ! $model->isDirty('created_by')) {
                $model->setAttribute('created_by', Auth::id());
            }
        });

        static::updating(function (Model $model): void {
            if (Auth::check()) {
                $model->setAttribute('updated_by', Auth::id());
            }
        });

        static::deleting(function (Model $model): void {
            if (Auth::check()) {
                $model->setAttribute('deleted_by', Auth::id());
                $model->saveQuietly();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deleter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
