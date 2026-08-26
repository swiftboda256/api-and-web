<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(UserSeeder::class);
        $this->call(ConfigurationSeeder::class);
        $this->call(VehicleTypeSeeder::class);
        $this->call(ServiceCatalogSeeder::class);
        $this->call(ZoneSeeder::class);
        $this->call(RiderSeeder::class);
        $this->call(PricingRuleSeeder::class);
        $this->call(SurgePricingScheduleSeeder::class);
        $this->call(PromoCodeSeeder::class);
        $this->call(TripCancellationReasonSeeder::class);
        $this->call(SupportCategorySeeder::class);
    }
}
