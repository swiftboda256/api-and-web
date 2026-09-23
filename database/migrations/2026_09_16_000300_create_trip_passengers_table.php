<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users');
            $table->unsignedTinyInteger('seats_requested')->default(1);
            $table->enum('status', [
                'requested',
                'matched',
                'arrived_pickup',
                'picked_up',
                'arrived_dropoff',
                'dropped_off',
                'cancelled',
            ])->default('requested');

            // The passenger's own segment, distinct from the vehicle's overall route. Fare
            // and payment data live on trip_fare_breakdowns instead (one row per passenger),
            // not here -- this table is lifecycle/identity only.
            $table->decimal('distance_km', 8, 2)->nullable();
            $table->unsignedInteger('duration_minutes')->nullable();

            $table->timestamp('requested_at');
            $table->timestamp('matched_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('dropped_off_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('cancellation_reason_id')->nullable()->constrained('trip_cancellation_reasons')->nullOnDelete();

            $table->auditColumns();

            $table->index(['trip_id', 'status']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_passengers');
    }
};
