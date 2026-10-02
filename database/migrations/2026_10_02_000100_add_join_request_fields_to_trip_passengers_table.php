<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ride-share passengers now need the driver's approval before joining an ongoing trip: a
 * new passenger starts as 'pending_approval' on the trip they were offered to, with the
 * offer's detour and expiry recorded here. matched_at doubles as the time the driver
 * accepted. declined_trip_ids lists trips whose driver declined (or didn't answer), so a
 * re-offer never goes back to them.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE trip_passengers DROP CONSTRAINT trip_passengers_status_check');
        DB::statement("ALTER TABLE trip_passengers ADD CONSTRAINT trip_passengers_status_check CHECK (status IN ('requested', 'pending_approval', 'matched', 'arrived_pickup', 'picked_up', 'arrived_dropoff', 'dropped_off', 'cancelled'))");

        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->unsignedInteger('detour_minutes')->nullable()->after('duration_minutes');
            $table->decimal('detour_km', 8, 2)->nullable()->after('detour_minutes');
            $table->timestamp('request_expires_at')->nullable()->after('requested_at');
            $table->json('declined_trip_ids')->nullable()->after('request_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->dropColumn(['detour_minutes', 'detour_km', 'request_expires_at', 'declined_trip_ids']);
        });

        DB::statement('ALTER TABLE trip_passengers DROP CONSTRAINT trip_passengers_status_check');
        DB::statement("ALTER TABLE trip_passengers ADD CONSTRAINT trip_passengers_status_check CHECK (status IN ('requested', 'matched', 'arrived_pickup', 'picked_up', 'arrived_dropoff', 'dropped_off', 'cancelled'))");
    }
};
