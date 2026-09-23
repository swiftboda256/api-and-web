<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            // Promo codes only ever apply to solo 'ride' bookings (ride-share pricing uses
            // its own discount_percentage instead, never stacked with a promo code) -- but
            // now that solo rides also settle through trip_passengers, this is where that
            // data belongs instead of trips.promo_code_id. The discount amount itself lives
            // on trip_fare_breakdowns, alongside the rest of the fare breakdown.
            $table->foreignId('promo_code_id')->nullable()->after('duration_minutes')->constrained('promo_codes')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
        });
    }
};
