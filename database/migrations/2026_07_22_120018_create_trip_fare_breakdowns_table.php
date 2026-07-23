<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_fare_breakdowns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('base_fare', 10, 2);
            $table->decimal('distance_fare', 10, 2)->default(0);
            $table->decimal('time_fare', 10, 2)->default(0);
            $table->decimal('surge_multiplier', 4, 2)->default(1);
            $table->decimal('surge_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('cancellation_fee', 10, 2)->default(0);
            $table->decimal('commission_rate', 5, 2)->default(0);
            $table->decimal('commission_amount', 10, 2)->default(0);
            $table->decimal('rider_earning', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->string('currency_code', 3);
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_fare_breakdowns');
    }
};
