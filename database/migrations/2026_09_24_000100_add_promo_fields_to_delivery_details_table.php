<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_details', function (Blueprint $table) {
            // Plain 'delivery' bookings support promo codes (delivery_share pricing uses its
            // own discount_percentage instead, never stacked) -- same gap already fixed for
            // trip_passengers.
            $table->foreignId('promo_code_id')->nullable()->after('discount_percentage')->constrained('promo_codes')->nullOnDelete();
            $table->decimal('discount_amount', 10, 2)->default(0)->after('promo_code_id');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_details', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promo_code_id');
            $table->dropColumn('discount_amount');
        });
    }
};
