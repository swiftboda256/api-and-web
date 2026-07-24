<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $systemUser = User::query()->firstOrCreate(
            ['phone' => 'system'],
            [
                'first_name' => 'System',
                'last_name' => 'Account',
                'email' => null,
                'status' => 'active',
                'allow_login' => false,
                'phone_verified_at' => null,
                'email_verified_at' => null,
            ],
        );

        if (! $systemUser->hasRole('system')) {
            $systemUser->assignRole('system');
        }

        $adminUser = User::query()->firstOrCreate(
            ['phone' => 'admin'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'email' => 'admin@swiftboda.app',
                'password' => Hash::make('@Dmin1234'),
                'login_type' => 'password',
                'status' => 'active',
                'allow_login' => true,
                'phone_verified_at' => now(),
                'email_verified_at' => now(),
            ],
        );

        if (! $adminUser->hasRole('admin')) {
            $adminUser->assignRole('admin');
        }
    }
}
