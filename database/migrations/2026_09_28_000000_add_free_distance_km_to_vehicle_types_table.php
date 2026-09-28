<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->decimal('free_distance_km', 8, 2)->default(0)->after('capacity');
        });

        DB::table('vehicle_types')->where('code', 'car')->update(['free_distance_km' => 4]);
        DB::table('vehicle_types')->where('code', 'motorcycle')->update(['free_distance_km' => 2]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vehicle_types', function (Blueprint $table) {
            $table->dropColumn('free_distance_km');
        });
    }
};
