<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Job offers sent to riders while a trip searches for one, so the backend knows who was
 * offered it: their phones ring until expires_at, and every rider whose offer is still
 * live gets a ride_offer_cancelled push when it's taken, cancelled or expires.
 *
 * trips.search_started_at marks the current search: it times out ("no riders found")
 * dispatch_search_timeout_minutes later, and a rider is offered a trip at most once per
 * search.
 *
 * trip_passengers.near_destination_notified_at records the one "almost there" push per
 * passenger.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_id')->constrained('users')->cascadeOnDelete();
            // pending: still ringing. accepted: this rider took the trip. expired: nobody
            // took it in time. withdrawn: another rider took it, or the trip was cancelled.
            $table->enum('status', ['pending', 'accepted', 'expired', 'withdrawn'])->default('pending');
            $table->timestamp('offered_at');
            $table->timestamp('expires_at');
            $table->timestamp('closed_at')->nullable();
            $table->auditColumns();

            $table->index(['trip_id', 'status']);
            $table->index(['rider_id', 'status', 'expires_at']);
            $table->index(['status', 'expires_at']);
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->timestamp('search_started_at')->nullable()->after('requested_at');
        });

        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->timestamp('near_destination_notified_at')->nullable()->after('picked_up_at');
        });
    }

    public function down(): void
    {
        Schema::table('trip_passengers', function (Blueprint $table) {
            $table->dropColumn('near_destination_notified_at');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('search_started_at');
        });

        Schema::dropIfExists('trip_offers');
    }
};
