<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // Ride-share only: seats still free on the vehicle for this trip right now,
            // and how many passengers (across trip_passengers) are currently matched/on board.
            $table->unsignedTinyInteger('available_seats')->nullable()->after('vehicle_type_id');
            $table->unsignedTinyInteger('passenger_count')->default(0)->after('available_seats');

            // Ride-share only: the vehicle's current planned route across all remaining
            // passenger stops, refreshed by RideShareMatchingService on every insert/removal.
            $table->text('route_polyline')->nullable()->after('dropoff_address');
            $table->decimal('route_distance_km', 8, 2)->nullable()->after('route_polyline');
            $table->unsignedInteger('route_duration_minutes')->nullable()->after('route_distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'available_seats',
                'passenger_count',
                'route_polyline',
                'route_distance_km',
                'route_duration_minutes',
            ]);
        });
    }
};
