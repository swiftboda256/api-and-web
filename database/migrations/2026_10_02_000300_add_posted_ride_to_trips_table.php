<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Posted rides: a trip the driver creates up front -- a fixed origin -> destination on a
 * set date, with the driver's own per-seat fare -- that customers browse and book seats
 * on (each booking needing the driver's approval). It's 'open' from posting until the
 * driver starts it, then runs exactly like a ride-share trip.
 *
 * The route lives on the trip itself since it exists before any passenger does; each
 * booking still gets its own trip_passengers/trip_stops/trip_fare_breakdowns rows,
 * copied from it.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_type_check');
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_type_check CHECK (type IN ('ride', 'delivery', 'ride_share', 'delivery_share', 'posted_ride'))");

        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_status_check');
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check CHECK (status IN ('requested', 'searching', 'open', 'accepted', 'arrived', 'in_progress', 'completed', 'cancelled'))");

        Schema::table('trips', function (Blueprint $table) {
            $table->timestamp('departs_at')->nullable()->after('requested_at');
            $table->decimal('seat_fare', 10, 2)->nullable()->after('available_cargo_weight_kg');
            $table->geometry('origin_location', subtype: 'point', srid: 4326)->nullable()->after('seat_fare');
            $table->string('origin_address')->nullable()->after('origin_location');
            $table->geometry('destination_location', subtype: 'point', srid: 4326)->nullable()->after('origin_address');
            $table->string('destination_address')->nullable()->after('destination_location');

            $table->index(['type', 'status', 'departs_at']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['type', 'status', 'departs_at']);
            $table->dropColumn([
                'departs_at',
                'seat_fare',
                'origin_location',
                'origin_address',
                'destination_location',
                'destination_address',
            ]);
        });

        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_status_check');
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_status_check CHECK (status IN ('requested', 'searching', 'accepted', 'arrived', 'in_progress', 'completed', 'cancelled'))");

        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_type_check');
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_type_check CHECK (type IN ('ride', 'delivery', 'ride_share', 'delivery_share'))");
    }
};
