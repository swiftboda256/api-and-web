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
                'value' => '3535353535',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the user app.',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'rider_test_number'],
            [
                'value' => '2525252525',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the rider app (motorcycle rider).',
                'is_public' => false,
            ],
        );

        Configuration::query()->firstOrCreate(
            ['key' => 'rider_test_number_car'],
            [
                'value' => '2525252526',
                'group' => 'auth',
                'description' => 'Apple and Google play store test number for the rider app (car rider).',
                'is_public' => false,
            ],
        );
    }
}
