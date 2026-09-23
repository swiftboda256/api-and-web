<?php

namespace App\Services\Auth;

use App\Jobs\ProcessAccountDeletionJob;
use App\Models\DeliveryDetails;
use App\Models\Trip;
use App\Models\TripPassenger;
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
            $user->notify(new AccountDeletionRequestedNotification);
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
        $activePassengerStatuses = ['requested', 'matched', 'arrived_pickup', 'picked_up', 'arrived_dropoff'];

        $userIdsWithActiveTripsAsRider = Trip::query()
            ->whereIn('rider_id', $userIds)
            ->whereIn('status', $activeStatuses)
            ->pluck('rider_id');

        $userIdsWithActiveTripsAsCustomer = TripPassenger::query()
            ->whereIn('customer_id', $userIds)
            ->whereIn('status', $activePassengerStatuses)
            ->pluck('customer_id')
            ->merge(
                DeliveryDetails::query()
                    ->whereIn('sender_id', $userIds)
                    ->whereIn('status', $activePassengerStatuses)
                    ->pluck('sender_id')
            );

        $userIdsWithActiveTrips = $userIdsWithActiveTripsAsRider
            ->merge($userIdsWithActiveTripsAsCustomer)
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
        if ($this->hasActiveTripAsCustomer($user->id) || $this->hasActiveTripAsRider($user->id)) {
            throw ValidationException::withMessages([
                'account' => 'You have an active trip. Please complete or cancel it before deleting your account.',
            ]);
        }
    }

    /**
     * A customer's own active segment -- trips carry no customer_id of their own since the
     * trip_passengers/delivery_details unification, so this checks their individual
     * passenger/delivery rows instead of the vehicle trip as a whole.
     */
    private function hasActiveTripAsCustomer(int $userId): bool
    {
        $activePassengerStatuses = ['requested', 'matched', 'arrived_pickup', 'picked_up', 'arrived_dropoff'];

        return TripPassenger::query()->where('customer_id', $userId)->whereIn('status', $activePassengerStatuses)->exists()
            || DeliveryDetails::query()->where('sender_id', $userId)->whereIn('status', $activePassengerStatuses)->exists();
    }

    private function hasActiveTripAsRider(int $userId): bool
    {
        $activeStatuses = ['requested', 'searching', 'accepted', 'arrived', 'in_progress'];

        return Trip::query()->where('rider_id', $userId)->whereIn('status', $activeStatuses)->exists();
    }
}
