<?php

namespace App\Services\Rider;

use App\Models\RiderProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Which riders sit in which KYC review tab. Shared by the KYC Reviews page, its nav badge and the
 * dashboard, so they always agree.
 *
 * - awaiting:   something is waiting on an admin (a pending required document or a pending vehicle)
 * - rejected:   nothing waiting, and KYC or the vehicle was rejected
 * - incomplete: nothing waiting or rejected, but a required document or the vehicle is missing
 * - approved:   KYC approved and vehicle approved
 */
class KycQueue
{
    public const array TABS = ['awaiting', 'incomplete', 'rejected', 'approved', 'all'];

    /**
     * Riders (users with a rider profile) in the given tab.
     *
     * @return Builder<User>
     */
    public static function query(string $tab = 'all'): Builder
    {
        $query = User::query()->whereHas('riderProfile');

        return match ($tab) {
            'awaiting' => self::awaiting($query),
            'incomplete' => self::incomplete(self::notAwaiting($query)->whereDoesntHave('riderProfile', self::rejectedConstraint(...))),
            'rejected' => self::notAwaiting($query)->whereHas('riderProfile', self::rejectedConstraint(...)),
            'approved' => $query->whereHas('riderProfile', fn (Builder $profile) => $profile
                ->where('kyc_status', 'approved')
                ->whereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('status', 'approved'))),
            default => $query,
        };
    }

    public static function awaitingCount(): int
    {
        return self::query('awaiting')->count();
    }

    /**
     * Adds `kyc_waiting_since`: when the oldest item still waiting was submitted (a pending required
     * document's or the pending vehicle's last update). Null when nothing is waiting.
     *
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    public static function withWaitingSince(Builder $query): Builder
    {
        $types = "'".implode("', '", RiderKycService::REQUIRED_DOCUMENT_TYPES)."'";

        return $query->addSelect([
            'kyc_waiting_since' => DB::table('rider_profiles')
                ->whereColumn('rider_profiles.user_id', 'users.id')
                ->selectRaw(<<<SQL
                    LEAST(
                        (SELECT MIN(documents.updated_at) FROM documents
                            WHERE documents.documentable_type = ? AND documents.documentable_id = rider_profiles.id
                            AND documents.status = 'pending' AND documents.deleted_at IS NULL
                            AND documents.document_type IN ({$types})),
                        (SELECT MIN(vehicles.updated_at) FROM vehicles
                            WHERE vehicles.rider_profile_id = rider_profiles.id
                            AND vehicles.status = 'pending' AND vehicles.deleted_at IS NULL)
                    )
                    SQL, [RiderProfile::class])
                ->limit(1),
        ]);
    }

    /**
     * @param  Builder<RiderProfile>  $profile
     */
    private static function rejectedConstraint(Builder $profile): void
    {
        $profile->where(fn (Builder $rejected) => $rejected
            ->where('kyc_status', 'rejected')
            ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('status', 'rejected')));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function awaiting(Builder $query): Builder
    {
        return $query->whereHas('riderProfile', fn (Builder $profile) => $profile
            ->whereHas('documents', fn (Builder $document) => $document
                ->whereIn('document_type', RiderKycService::REQUIRED_DOCUMENT_TYPES)
                ->where('status', 'pending'))
            ->orWhereHas('vehicle', fn (Builder $vehicle) => $vehicle->where('status', 'pending')));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function notAwaiting(Builder $query): Builder
    {
        return $query->whereNotIn('users.id', self::awaiting(User::query())->select('users.id'));
    }

    /**
     * @param  Builder<User>  $query
     * @return Builder<User>
     */
    private static function incomplete(Builder $query): Builder
    {
        return $query->whereHas('riderProfile', function (Builder $profile): void {
            $profile->where(function (Builder $missing): void {
                foreach (RiderKycService::REQUIRED_DOCUMENT_TYPES as $type) {
                    $missing->orWhereDoesntHave('documents', fn (Builder $document) => $document->where('document_type', $type));
                }

                $missing->orDoesntHave('vehicle');
            });
        });
    }
}
