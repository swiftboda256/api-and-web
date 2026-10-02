<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A ride-share passenger waiting on the driver's approval keeps their pickup/dropoff as
 * trip_stops with a NULL sequence -- recorded, but not on the route yet. The sequence is
 * assigned when the driver accepts. Postgres allows any number of NULLs under the
 * unique(trip_id, sequence) index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trip_stops', function (Blueprint $table) {
            $table->unsignedTinyInteger('sequence')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trip_stops', function (Blueprint $table) {
            $table->unsignedTinyInteger('sequence')->nullable(false)->change();
        });
    }
};
