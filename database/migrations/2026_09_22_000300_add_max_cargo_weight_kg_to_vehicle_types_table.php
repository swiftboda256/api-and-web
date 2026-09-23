<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            // Null means this vehicle type doesn't support delivery pooling.
            $table->decimal('max_cargo_weight_kg', 8, 2)->nullable()->after('capacity');
        });
    }

    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropColumn('max_cargo_weight_kg');
        });
    }
};
