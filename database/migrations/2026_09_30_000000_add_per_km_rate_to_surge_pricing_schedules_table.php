<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surge_pricing_schedules', function (Blueprint $table) {
            $table->decimal('per_km_rate', 10, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('surge_pricing_schedules', function (Blueprint $table) {
            $table->dropColumn('per_km_rate');
        });
    }
};
