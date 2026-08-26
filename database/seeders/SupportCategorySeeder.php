<?php

namespace Database\Seeders;

use App\Models\SupportCategory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class SupportCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['code' => 'trip_issue', 'name' => 'Trip Issue'],
            ['code' => 'payment', 'name' => 'Payment'],
            ['code' => 'account', 'name' => 'Account'],
            ['code' => 'vehicle', 'name' => 'Vehicle'],
            ['code' => 'other', 'name' => 'Other'],
        ];

        /** @var User $systemUser */
        $systemUser = User::role('system')->firstOrFail();

        $actingUser = Auth::user();
        Auth::setUser($systemUser);

        try {
            foreach ($categories as $category) {
                SupportCategory::query()->firstOrCreate(
                    ['code' => $category['code']],
                    [
                        'name' => $category['name'],
                        'is_active' => true,
                    ],
                );
            }
        } finally {
            if ($actingUser instanceof User) {
                Auth::setUser($actingUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
