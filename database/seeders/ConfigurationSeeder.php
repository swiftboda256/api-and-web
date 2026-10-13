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
            ['key' => 'wallet_topups_enabled'],
            [
                'value' => false,
                'value_type' => 'bool',
                'group' => 'wallet',
                'description' => 'When false, all wallet top-ups are rejected with a "temporarily unavailable" error.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'wallet_withdrawals_enabled'],
            [
                'value' => false,
                'value_type' => 'bool',
                'group' => 'wallet',
                'description' => 'When false, all wallet withdrawals are rejected with a "temporarily unavailable" error.',
                'is_public' => false,
            ],
        );
    }
}
