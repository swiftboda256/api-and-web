<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A cancelled delivery keeps its unvisited pickup/dropoff stops (so the addresses stay
 * visible in history) with a NULL sequence, taking them off the vehicle's route -- the
 * same convention trip_stops uses. Postgres allows any number of NULLs under the
 * unique(trip_id, sequence) index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->unsignedTinyInteger('sequence')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_stops', function (Blueprint $table) {
            $table->unsignedTinyInteger('sequence')->nullable(false)->change();
        });
    }
};
