<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $table) {
            $table->id();
            $table->string('trip_number')->unique();
            $table->foreignId('customer_id')->constrained('users');
            $table->foreignId('rider_profile_id')->nullable()->constrained('rider_profiles')->nullOnDelete();
            $table->foreignId('vehicle_id')->nullable()->constrained('vehicles')->nullOnDelete();
            $table->foreignId('zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->enum('type', ['ride', 'delivery']);
            $table->enum('status', ['requested', 'searching', 'accepted', 'arrived', 'in_progress', 'completed', 'cancelled'])->default('requested');
            $table->geometry('pickup_location', subtype: 'point', srid: 4326);
            $table->string('pickup_address')->nullable();
            $table->geometry('dropoff_location', subtype: 'point', srid: 4326);
            $table->string('dropoff_address')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancellation_reason_id')->nullable()->constrained('trip_cancellation_reasons')->nullOnDelete();
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();
            $table->decimal('estimated_fare', 10, 2);
            $table->decimal('final_fare', 10, 2)->nullable();
            $table->string('currency_code', 3);
            $table->foreignId('promo_code_id')->nullable()->constrained('promo_codes')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->enum('payment_method', ['wallet', 'cash', 'mobile_money', 'card'])->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'failed', 'refunded'])->default('pending');
            $table->auditColumns();

            $table->spatialIndex('pickup_location');
            $table->spatialIndex('dropoff_location');
            $table->index(['status']);
            $table->index(['customer_id', 'status']);
            $table->index(['rider_profile_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
