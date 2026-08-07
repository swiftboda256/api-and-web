<?php

namespace Database\Seeders;

use App\Models\SupportCategory;
use Illuminate\Database\Seeder;

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

        foreach ($categories as $category) {
            SupportCategory::query()->firstOrCreate(
                ['code' => $category['code']],
                [
                    'name' => $category['name'],
                    'is_active' => true,
                ],
            );
        }
    }
}
