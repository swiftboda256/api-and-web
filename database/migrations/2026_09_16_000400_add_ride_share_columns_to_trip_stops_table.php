<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_stops', function (Blueprint $table) {
            // Null for solo ride/delivery stops. Set for ride-share stops, one row per
            // passenger pickup and one per passenger dropoff, ordered by `sequence`.
            $table->foreignId('trip_passenger_id')->nullable()->after('trip_id')->constrained()->cascadeOnDelete();
            $table->enum('stop_type', ['pickup', 'dropoff'])->nullable()->after('trip_passenger_id');

            // +seats_requested on a pickup stop, -seats_requested on the matching dropoff stop.
            // Lets the matching service recompute running vehicle occupancy along the sequence
            // as a cheap running sum instead of re-querying trip_passengers on every insert.
            $table->smallInteger('seats_delta')->default(0)->after('stop_type');
        });
    }

    public function down(): void
    {
        Schema::table('trip_stops', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_passenger_id');
            $table->dropColumn(['stop_type', 'seats_delta']);
        });
    }
};
