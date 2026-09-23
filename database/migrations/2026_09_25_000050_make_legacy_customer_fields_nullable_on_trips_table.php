<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Relaxes trips' pre-unification customer/pickup/dropoff/fare columns to nullable --
     * new bookings (TripService::book()) no longer populate them at all, so they'd fail
     * their NOT NULL constraints otherwise. Deliberately separate from (and dated before)
     * the migration that drops these columns entirely: that one is guarded on every trip
     * having been backfilled onto trip_passengers/delivery_details first, which won't be
     * true the moment this deploy lands in production -- this migration is what keeps new
     * bookings working in the gap between deploying this code and running the backfill.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE trips ALTER COLUMN customer_id DROP NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN pickup_location DROP NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN dropoff_location DROP NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN estimated_fare DROP NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN currency_code DROP NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN discount_amount DROP NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN payment_status DROP NOT NULL');
    }

    public function down(): void
    {
        // Only safe to reverse if no row has actually been left null in the meantime.
        DB::statement('ALTER TABLE trips ALTER COLUMN customer_id SET NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN pickup_location SET NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN dropoff_location SET NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN estimated_fare SET NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN currency_code SET NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN discount_amount SET NOT NULL');
        DB::statement('ALTER TABLE trips ALTER COLUMN payment_status SET NOT NULL');
    }
};
