<?php

namespace App\Services\Auth;

use App\Jobs\ProcessAccountDeletionJob;
use App\Models\Trip;
use App\Models\User;
use App\Models\UserDeleteRequest;
use App\Notifications\AccountDeletionRequestedNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
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
            $user->notify(new AccountDeletionRequestedNotification());
        }

        ProcessAccountDeletionJob::dispatch($deleteRequest->id);

        return $deleteRequest;
    }

    public function bulkDelete(array $userIds, ?string $reason = null): Collection
    {
        $userIds = array_values(array_unique($userIds));

        $alreadyRequestedUserIds = UserDeleteRequest::query()
            ->whereIn('user_id', $userIds)
            ->where('status', 'pending')
            ->pluck('user_id')
            ->all();

        $activeStatuses = ['requested', 'searching', 'accepted', 'arrived', 'in_progress'];

        $userIdsWithActiveTrips = Trip::query()
            ->where(function ($query) use ($userIds): void {
                $query->whereIn('customer_id', $userIds)
                    ->orWhereIn('rider_id', $userIds);
            })
            ->whereIn('status', $activeStatuses)
            ->get(['customer_id', 'rider_id'])
            ->flatMap(fn (Trip $trip): array => [$trip->customer_id, $trip->rider_id])
            ->filter()
            ->unique()
            ->all();

        $userIdsToDelete = array_values(array_diff($userIds, $alreadyRequestedUserIds, $userIdsWithActiveTrips));

        $existingDeleteRequests = UserDeleteRequest::query()
            ->whereIn('user_id', $alreadyRequestedUserIds)
            ->where('status', 'pending')
            ->get();

        if ($userIdsToDelete === []) {
            return $existingDeleteRequests;
        }

        $deleteRequests = DB::transaction(function () use ($userIdsToDelete, $reason): Collection {
            $now = now();
            $createdBy = Auth::id();

            $rows = [];

            foreach ($userIdsToDelete as $userId) {
                $rows[] = [
                    'user_id' => $userId,
                    'status' => 'pending',
                    'reason' => $reason,
                    'requested_at' => $now,
                    'scheduled_for' => $now,
                    'created_by' => $createdBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            UserDeleteRequest::query()->insert($rows);

            User::query()->whereIn('id', $userIdsToDelete)->update([
                'status' => 'deleted',
                'allow_login' => false,
            ]);

            return UserDeleteRequest::query()
                ->whereIn('user_id', $userIdsToDelete)
                ->where('status', 'pending')
                ->get();
        });

        foreach ($deleteRequests as $deleteRequest) {
            ProcessAccountDeletionJob::dispatch($deleteRequest->id);
        }

        return $existingDeleteRequests->merge($deleteRequests);
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
