<?php

namespace Database\Seeders;

use App\Models\Configuration;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            $this->seedConfigurations();
        } finally {
            if ($actingUser instanceof User) {
                Auth::setUser($actingUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }

    private function seedConfigurations(): void
    {
        Configuration::query()->firstOrCreate(
            ['key' => 'mock_otp'],
            [
                'value' => false,
                'value_type' => 'bool',
                'group' => 'otp',
                'description' => 'When true, OTPs are not actually sent and verification accepts any code. Never enable in production.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'sanctum_token_expiration_days'],
            [
                'value' => 30,
                'value_type' => 'number',
                'group' => 'auth',
                'description' => 'Number of days before an issued Sanctum API token expires. Set to 0 for tokens that never expire.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'privacy_contact_email'],
            [
                'value' => 'swiftboda256@gmail.com',
                'value_type' => 'text',
                'group' => 'privacy',
                'description' => 'Email address displayed in the public privacy policy for privacy requests and questions.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'dispatch_radius_km'],
            [
                'value' => 50,
                'value_type' => 'number',
                'group' => 'trip',
                'description' => 'Radius in kilometers used to notify nearby riders when a trip is dispatched.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'dispatch_offer_window_seconds'],
            [
                'value' => 30,
                'value_type' => 'number',
                'group' => 'trip',
                'description' => 'Seconds a job offer rings on a rider\'s phone. If nobody accepts in that time, the trip is offered to riders who have become available since.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'dispatch_search_timeout_minutes'],
            [
                'value' => 5,
                'value_type' => 'number',
                'group' => 'trip',
                'description' => 'Minutes a trip searches for a rider before it is cancelled and the customer is told no riders were found.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'near_destination_radius_meters'],
            [
                'value' => 400,
                'value_type' => 'number',
                'group' => 'trip',
                'description' => 'Distance in meters from a passenger\'s drop-off at which they get the one "almost there" push.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'search_radius_km'],
            [
                'value' => 50,
                'value_type' => 'number',
                'group' => 'trip',
                'description' => 'Radius in kilometers used when customers search for nearby riders and when riders search for nearby placed trips.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'round_fare_to_nearest_500'],
            [
                'value' => true,
                'value_type' => 'bool',
                'group' => 'trip',
                'description' => 'When true, a trip\'s final fare is rounded down to the nearest 500 (e.g. 2700 becomes 2500) before payment is settled.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'user_test_number'],
            [
                'value' => '353535353',
                'value_type' => 'text',
                'guard' => 'user_app',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the user app.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'rider_test_number'],
            [
                'value' => '252525252',
                'value_type' => 'text',
                'guard' => 'rider_app',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the rider app (motorcycle rider).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'rider_test_number_car'],
            [
                'value' => '252525256',
                'value_type' => 'text',
                'guard' => 'rider_app',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the rider app (car rider).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_discount_percentage'],
            [
                'value' => 20,
                'value_type' => 'number',
                'group' => 'ride_share',
                'description' => 'Percentage discount applied to a passenger\'s own segment fare when they share a ride, relative to what that segment would cost as a solo ride.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_max_detour_minutes'],
            [
                'value' => 7,
                'value_type' => 'number',
                'group' => 'ride_share',
                'description' => 'Maximum extra driving time (minutes) a new passenger\'s pickup+dropoff may add to an ongoing ride-share trip\'s remaining route before it is rejected as a match.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_max_detour_km'],
            [
                'value' => 3,
                'value_type' => 'number',
                'group' => 'ride_share',
                'description' => 'Maximum extra driving distance (km) a new passenger\'s pickup+dropoff may add to an ongoing ride-share trip\'s remaining route before it is rejected as a match.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_candidate_search_radius_km'],
            [
                'value' => 5,
                'value_type' => 'number',
                'group' => 'ride_share',
                'description' => 'Radius in kilometers around a new ride-share request\'s pickup point used to shortlist ongoing ride-share trips as merge candidates, before the more expensive routing/detour check runs.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_max_bearing_deviation_degrees'],
            [
                'value' => 100,
                'value_type' => 'number',
                'group' => 'ride_share',
                'description' => 'Maximum degrees between an ongoing ride-share trip\'s direction of travel and the bearing to a new pickup, before it\'s rejected as going the wrong way (cheap pre-filter, no routing call).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_max_candidates'],
            [
                'value' => 5,
                'value_type' => 'number',
                'group' => 'ride_share',
                'description' => 'Maximum number of nearby ongoing ride-share trips evaluated with a routing/detour check per request, to bound routing API calls.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_join_request_timeout_seconds'],
            [
                'value' => 30,
                'value_type' => 'number',
                'group' => 'ride_share',
                'description' => 'Seconds a driver has to accept a passenger\'s request to join their ongoing ride-share trip before it counts as declined and is offered to the next trip.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'posted_ride_request_timeout_minutes'],
            [
                'value' => 60,
                'value_type' => 'number',
                'group' => 'posted_ride',
                'description' => 'Minutes a driver has to approve a seat booking on their posted ride before it expires and the seats are released. Never later than the ride\'s departure time.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'posted_ride_search_radius_km'],
            [
                'value' => 5,
                'value_type' => 'number',
                'group' => 'posted_ride',
                'description' => 'Radius (km) around the customer\'s chosen origin/destination within which posted rides are returned when browsing.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_discount_percentage'],
            [
                'value' => 20,
                'value_type' => 'number',
                'group' => 'delivery_share',
                'description' => 'Percentage discount applied to a delivery\'s own segment fare when it\'s pooled, relative to what that segment would cost as a standalone delivery.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_max_detour_minutes'],
            [
                'value' => 10,
                'value_type' => 'number',
                'group' => 'delivery_share',
                'description' => 'Maximum extra driving time (minutes) a new delivery\'s pickup+dropoff may add to an ongoing pooled-delivery trip\'s remaining route before it is rejected as a match.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_max_detour_km'],
            [
                'value' => 5,
                'value_type' => 'number',
                'group' => 'delivery_share',
                'description' => 'Maximum extra driving distance (km) a new delivery\'s pickup+dropoff may add to an ongoing pooled-delivery trip\'s remaining route before it is rejected as a match.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_candidate_search_radius_km'],
            [
                'value' => 5,
                'value_type' => 'number',
                'group' => 'delivery_share',
                'description' => 'Radius in kilometers around a new pooled-delivery request\'s pickup point used to shortlist ongoing pooled-delivery trips as merge candidates.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_max_bearing_deviation_degrees'],
            [
                'value' => 100,
                'value_type' => 'number',
                'group' => 'delivery_share',
                'description' => 'Maximum degrees between an ongoing pooled-delivery trip\'s direction of travel and the bearing to a new pickup, before it\'s rejected as going the wrong way.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_max_candidates'],
            [
                'value' => 5,
                'value_type' => 'number',
                'group' => 'delivery_share',
                'description' => 'Maximum number of nearby ongoing pooled-delivery trips evaluated with a routing/detour check per request, to bound routing API calls.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'wallet_daily_topup_limit'],
            [
                'value' => 10000,
                'value_type' => 'number',
                'group' => 'wallet',
                'description' => 'Maximum total amount (UGX) a user can top up into their wallet per day. Pending and completed top-ups count towards it.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'wallet_daily_withdrawal_limit'],
            [
                'value' => 10000,
                'value_type' => 'number',
                'group' => 'wallet',
                'description' => 'Maximum total amount (UGX, excluding charges) a user can withdraw from their wallet per day. Processing and completed withdrawals count towards it.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'wallet_weekly_topup_limit'],
            [
                'value' => 50000,
                'value_type' => 'number',
                'group' => 'wallet',
                'description' => 'Maximum total amount (UGX) a user can top up into their wallet per week (Monday to Sunday). Pending and completed top-ups count towards it.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'wallet_weekly_withdrawal_limit'],
            [
                'value' => 50000,
                'value_type' => 'number',
                'group' => 'wallet',
                'description' => 'Maximum total amount (UGX, excluding charges) a user can withdraw from their wallet per week (Monday to Sunday). Processing and completed withdrawals count towards it.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'wallet_daily_limits_enabled'],
            [
                'value' => true,
                'value_type' => 'bool',
                'group' => 'wallet',
                'description' => 'When false, the daily top-up and withdrawal limits are not enforced.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'wallet_weekly_limits_enabled'],
            [
                'value' => true,
                'value_type' => 'bool',
                'group' => 'wallet',
                'description' => 'When false, the weekly top-up and withdrawal limits are not enforced.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'user_wallet_topups_enabled'],
            [
                'value' => false,
                'value_type' => 'bool',
                'guard' => 'user_app',
                'group' => 'wallet',
                'description' => 'When false, all wallet top-ups from the user app are rejected with a "temporarily unavailable" error.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'user_wallet_withdrawals_enabled'],
            [
                'value' => false,
                'value_type' => 'bool',
                'guard' => 'user_app',
                'group' => 'wallet',
                'description' => 'When false, all wallet withdrawals from the user app are rejected with a "temporarily unavailable" error.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'rider_wallet_topups_enabled'],
            [
                'value' => false,
                'value_type' => 'bool',
                'guard' => 'rider_app',
                'group' => 'wallet',
                'description' => 'When false, all wallet top-ups from the rider app are rejected with a "temporarily unavailable" error.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'rider_wallet_withdrawals_enabled'],
            [
                'value' => false,
                'value_type' => 'bool',
                'guard' => 'rider_app',
                'group' => 'wallet',
                'description' => 'When false, all wallet withdrawals from the rider app are rejected with a "temporarily unavailable" error.',
                'is_public' => false,
            ],
        );
    }
}
