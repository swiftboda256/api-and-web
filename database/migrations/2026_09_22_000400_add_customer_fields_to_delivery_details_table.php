<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A pooled delivery trip has many deliveries per trip_id, so this can no longer
        // be a one-to-one relationship.
        Schema::table('delivery_details', function (Blueprint $table) {
            $table->dropUnique(['trip_id']);
        });

        Schema::table('delivery_details', function (Blueprint $table) {
            $table->foreignId('sender_id')->nullable()->after('trip_id')->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'requested',
                'matched',
                'arrived_pickup',
                'picked_up',
                'arrived_dropoff',
                'dropped_off',
                'cancelled',
            ])->default('requested')->after('sender_id');

            $table->geometry('pickup_location', subtype: 'point', srid: 4326)->nullable()->after('status');
            $table->string('pickup_address')->nullable()->after('pickup_location');
            $table->geometry('dropoff_location', subtype: 'point', srid: 4326)->nullable()->after('pickup_address');
            $table->string('dropoff_address')->nullable()->after('dropoff_location');

            $table->decimal('distance_km', 8, 2)->nullable()->after('dropoff_address');
            $table->unsignedInteger('duration_minutes')->nullable()->after('distance_km');
            $table->decimal('base_fare_amount', 10, 2)->nullable()->after('duration_minutes');
            $table->decimal('discount_percentage', 5, 2)->nullable()->after('base_fare_amount');
            $table->decimal('estimated_fare', 10, 2)->nullable()->after('discount_percentage');
            $table->decimal('final_fare', 10, 2)->nullable()->after('estimated_fare');
            $table->string('currency_code', 3)->nullable()->after('final_fare');

            $table->enum('payment_method', ['wallet', 'cash', 'mobile_money', 'card'])->nullable()->after('currency_code');
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending')->after('payment_method');

            $table->timestamp('requested_at')->nullable()->after('payment_status');
            $table->timestamp('matched_at')->nullable()->after('requested_at');
            $table->timestamp('picked_up_at')->nullable()->after('matched_at');
            $table->timestamp('dropped_off_at')->nullable()->after('picked_up_at');
            $table->timestamp('cancelled_at')->nullable()->after('dropped_off_at');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->foreignId('cancellation_reason_id')->nullable()->after('cancelled_by')->constrained('trip_cancellation_reasons')->nullOnDelete();

            $table->index(['trip_id', 'status']);
            $table->index(['sender_id', 'status']);
            $table->spatialIndex('pickup_location');
            $table->spatialIndex('dropoff_location');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_details', function (Blueprint $table) {
            $table->dropSpatialIndex(['dropoff_location']);
            $table->dropSpatialIndex(['pickup_location']);
            $table->dropIndex(['sender_id', 'status']);
            $table->dropIndex(['trip_id', 'status']);

            $table->dropConstrainedForeignId('cancellation_reason_id');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn([
                'cancelled_at',
                'dropped_off_at',
                'picked_up_at',
                'matched_at',
                'requested_at',
                'payment_status',
                'payment_method',
                'currency_code',
                'final_fare',
                'estimated_fare',
                'discount_percentage',
                'base_fare_amount',
                'duration_minutes',
                'distance_km',
                'dropoff_address',
                'dropoff_location',
                'pickup_address',
                'pickup_location',
                'status',
            ]);
            $table->dropConstrainedForeignId('sender_id');
        });

        Schema::table('delivery_details', function (Blueprint $table) {
            $table->unique('trip_id');
        });
    }
};
