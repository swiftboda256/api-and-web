<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            // The old unique(trip_id, rater_id) assumed one rater per trip. On a ride-share
            // trip the driver (one rater_id) rates each of several passengers (several
            // ratee_id) on the same trip_id, so the constraint must include ratee_id too.
            // (A rating is tied to the specific passenger/delivery segment it was given for
            // via rater_id/ratee_id alone -- no separate trip_passenger_id column needed.)
            $table->dropUnique(['trip_id', 'rater_id']);
            $table->unique(['trip_id', 'rater_id', 'ratee_id']);
        });
    }

    public function down(): void
    {
        Schema::table('ratings', function (Blueprint $table) {
            $table->dropUnique(['trip_id', 'rater_id', 'ratee_id']);
            $table->unique(['trip_id', 'rater_id']);
        });
    }
};
