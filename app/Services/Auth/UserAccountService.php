<?php

namespace App\Services\Auth;

use App\Jobs\ProcessAccountDeletionJob;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserDeleteRequest;
use App\Notifications\AccountDeletionRequestedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccountService
{
    public function delete(User $user, ?string $reason = null): UserDeleteRequest
    {
        $existing = $user->deleteRequests()->where('status', 'pending')->first();

        if ($existing !== null) {
            return $existing;
        }

        $this->ensureNoActiveTrip($user);

        $deleteRequest = DB::transaction(function () use ($user, $reason): UserDeleteRequest {
            $deleteRequest = UserDeleteRequest::query()->create([
                'user_id' => $user->id,
                'status' => 'pending',
                'reason' => $reason,
                'requested_at' => now(),
                'scheduled_for' => now(),
            ]);

            $user->status = 'requested_delete';
            $user->allow_login = false;
            $user->save();

            return $deleteRequest;
        });

        if (filled($user->email)) {
            $user->notify(new AccountDeletionRequestedNotification($deleteRequest));
        }

        ProcessAccountDeletionJob::dispatch($deleteRequest->id);

        return $deleteRequest;
    }

    public function ensureNoActiveTrip(User $user): void
    {
        $activeStatuses = ['requested', 'searching', 'accepted', 'arrived', 'in_progress'];

        $hasActiveTrip = Trip::query()
            ->where(function ($query) use ($user): void {
                $query->where('customer_id', $user->id)
                    ->orWhere('rider_id', $user->id);
            })
            ->whereIn('status', $activeStatuses)
            ->exists();

        if ($hasActiveTrip) {
            throw ValidationException::withMessages([
                'account' => 'You have an active trip. Please complete or cancel it before deleting your account.',
            ]);
        }
    }
}
