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
                'group' => 'otp',
                'description' => 'When true, OTPs are not actually sent and verification accepts any code. Never enable in production.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'sanctum_token_expiration_days'],
            [
                'value' => 30,
                'group' => 'auth',
                'description' => 'Number of days before an issued Sanctum API token expires. Set to 0 for tokens that never expire.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'privacy_contact_email'],
            [
                'value' => 'swiftboda256@gmail.com',
                'group' => 'privacy',
                'description' => 'Email address displayed in the public privacy policy for privacy requests and questions.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'dispatch_radius_km'],
            [
                'value' => 50,
                'group' => 'trip',
                'description' => 'Radius in kilometers used to notify nearby riders when a trip is dispatched.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'search_radius_km'],
            [
                'value' => 50,
                'group' => 'trip',
                'description' => 'Radius in kilometers used when customers search for nearby riders and when riders search for nearby placed trips.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'free_distance_km'],
            [
                'value' => 4,
                'group' => 'trip',
                'description' => 'Distance in kilometers covered by the base fare before the per-km distance charge starts applying (car).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'free_distance_km_motorcycle'],
            [
                'value' => 2,
                'group' => 'trip',
                'description' => 'Distance in kilometers covered by the base fare before the per-km distance charge starts applying (motorcycle).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'round_fare_to_nearest_500'],
            [
                'value' => true,
                'group' => 'trip',
                'description' => 'When true, a trip\'s final fare is rounded down to the nearest 500 (e.g. 2700 becomes 2500) before payment is settled.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'user_test_number'],
            [
                'value' => '353535353',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the user app.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'rider_test_number'],
            [
                'value' => '252525252',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the rider app (motorcycle rider).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'rider_test_number_car'],
            [
                'value' => '252525256',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the rider app (car rider).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_discount_percentage'],
            [
                'value' => 20,
                'group' => 'ride_share',
                'description' => 'Percentage discount applied to a passenger\'s own segment fare when they share a ride, relative to what that segment would cost as a solo ride.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_max_detour_minutes'],
            [
                'value' => 7,
                'group' => 'ride_share',
                'description' => 'Maximum extra driving time (minutes) a new passenger\'s pickup+dropoff may add to an ongoing ride-share trip\'s remaining route before it is rejected as a match.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_max_detour_km'],
            [
                'value' => 3,
                'group' => 'ride_share',
                'description' => 'Maximum extra driving distance (km) a new passenger\'s pickup+dropoff may add to an ongoing ride-share trip\'s remaining route before it is rejected as a match.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_candidate_search_radius_km'],
            [
                'value' => 5,
                'group' => 'ride_share',
                'description' => 'Radius in kilometers around a new ride-share request\'s pickup point used to shortlist ongoing ride-share trips as merge candidates, before the more expensive routing/detour check runs.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_max_bearing_deviation_degrees'],
            [
                'value' => 100,
                'group' => 'ride_share',
                'description' => 'Maximum degrees between an ongoing ride-share trip\'s direction of travel and the bearing to a new pickup, before it\'s rejected as going the wrong way (cheap pre-filter, no routing call).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'ride_share_max_candidates'],
            [
                'value' => 5,
                'group' => 'ride_share',
                'description' => 'Maximum number of nearby ongoing ride-share trips evaluated with a routing/detour check per request, to bound routing API calls.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_discount_percentage'],
            [
                'value' => 20,
                'group' => 'delivery_share',
                'description' => 'Percentage discount applied to a delivery\'s own segment fare when it\'s pooled, relative to what that segment would cost as a standalone delivery.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_max_detour_minutes'],
            [
                'value' => 10,
                'group' => 'delivery_share',
                'description' => 'Maximum extra driving time (minutes) a new delivery\'s pickup+dropoff may add to an ongoing pooled-delivery trip\'s remaining route before it is rejected as a match.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_max_detour_km'],
            [
                'value' => 5,
                'group' => 'delivery_share',
                'description' => 'Maximum extra driving distance (km) a new delivery\'s pickup+dropoff may add to an ongoing pooled-delivery trip\'s remaining route before it is rejected as a match.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_candidate_search_radius_km'],
            [
                'value' => 5,
                'group' => 'delivery_share',
                'description' => 'Radius in kilometers around a new pooled-delivery request\'s pickup point used to shortlist ongoing pooled-delivery trips as merge candidates.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_max_bearing_deviation_degrees'],
            [
                'value' => 100,
                'group' => 'delivery_share',
                'description' => 'Maximum degrees between an ongoing pooled-delivery trip\'s direction of travel and the bearing to a new pickup, before it\'s rejected as going the wrong way.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'delivery_share_max_candidates'],
            [
                'value' => 5,
                'group' => 'delivery_share',
                'description' => 'Maximum number of nearby ongoing pooled-delivery trips evaluated with a routing/detour check per request, to bound routing API calls.',
                'is_public' => false,
            ],
        );
    }
}
