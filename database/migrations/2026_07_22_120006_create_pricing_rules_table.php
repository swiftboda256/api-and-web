<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zone_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('base_fare', 10, 2);
            $table->decimal('per_km_rate', 10, 2);
            $table->decimal('per_minute_rate', 10, 2);
            $table->decimal('minimum_fare', 10, 2);
            $table->decimal('cancellation_fee', 10, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('surge_multiplier', 4, 2)->default(1);
            $table->string('currency_code', 3);
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('effective_to')->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();

            $table->index(['zone_id', 'vehicle_type_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pricing_rules');
    }
};
