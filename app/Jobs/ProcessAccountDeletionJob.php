<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\UserDeleteRequest;
use App\Services\Auth\UserAccountService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Processes one UserDeleteRequest: dispatched immediately by UserAccountService::delete(), it
 * revokes login, soft deletes the user (rider or customer) and everything owned by it, and marks
 * the request completed. Trips, transactions, withdrawal requests and ratings are intentionally
 * left untouched since they are financial/audit records that must survive account deletion.
 *
 * Re-entrant by design (every step only acts on rows that still exist, so a repeat run is a
 * no-op) so it is safe for the queue to retry this job. There is no scheduler behind this job -
 * a request that can't complete (e.g. blocked by an active trip, or exhausting the queue's own
 * retry attempts) is left 'failed' as a permanent, audit-visible record requiring manual follow-up.
 */
class ProcessAccountDeletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userDeleteRequestId,
    ) {}

    public function handle(UserAccountService $userAccountService): void
    {
        $claimed = UserDeleteRequest::query()
            ->where('id', $this->userDeleteRequestId)
            ->whereIn('status', ['pending', 'processing'])
            ->update(['status' => 'processing', 'processing_at' => now()]);

        if ($claimed === 0) {
            return;
        }

        $deleteRequest = UserDeleteRequest::query()->find($this->userDeleteRequestId);

        if ($deleteRequest === null) {
            return;
        }

        $user = User::withTrashed()->find($deleteRequest->user_id);

        if ($user === null) {
            Log::error('account_deletion.user_missing', [
                'user_delete_request_id' => $deleteRequest->id,
            ]);

            $deleteRequest->update([
                'status' => 'failed',
                'failure_reason' => 'User record no longer exists.',
            ]);

            return;
        }

        $user->loadMissing('riderProfile.vehicle', 'riderProfile.documents');

        try {
            $userAccountService->ensureNoActiveTrip($user);
        } catch (ValidationException $e) {
            Log::error('account_deletion.blocked', [
                'user_delete_request_id' => $deleteRequest->id,
                'reason' => $e->getMessage(),
            ]);

            $deleteRequest->update([
                'status' => 'failed',
                'failure_reason' => $e->getMessage(),
            ]);

            return;
        }

        DB::transaction(function () use ($user): void {
            $riderProfile = $user->riderProfile;

            if ($riderProfile !== null) {
                $riderProfile->vehicle?->delete();
                $riderProfile->documents()->delete();
                $riderProfile->delete();
            }

            $user->wallet?->delete();
            $user->userSetting?->delete();
            $user->addresses()->delete();
            $user->emergencyContacts()->delete();
            $user->devices()->delete();

            $user->tokens()->delete();

            $user->forceFill([
                'phone' => 'deleted-user-'.$user->id,
                'email' => null,
                'avatar_url' => null,
                'referral_code' => null,
                'allow_login' => false,
                'status' => 'deleted',
            ])->save();

            $user->delete();

            Storage::disk('public')->deleteDirectory("avatars/{$user->id}");

            if ($riderProfile !== null) {
                Storage::disk('public')->deleteDirectory("documents/riders/{$riderProfile->id}");
            }
        });

        $deleteRequest->update(['status' => 'completed', 'completed_at' => now()]);
    }

    public function failed(Throwable $exception): void
    {
        Log::error('account_deletion.failed', [
            'user_delete_request_id' => $this->userDeleteRequestId,
            'error' => $exception->getMessage(),
        ]);

        UserDeleteRequest::query()
            ->where('id', $this->userDeleteRequestId)
            ->update([
                'status' => 'failed',
                'failure_reason' => $exception->getMessage(),
            ]);
    }
}
