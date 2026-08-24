<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('surge_pricing_schedules', function (Blueprint $table) {
            $table->decimal('fixed_amount', 8, 2)->default(0)->after('multiplier');
        });
    }

    public function down(): void
    {
        Schema::table('surge_pricing_schedules', function (Blueprint $table) {
            $table->dropColumn('fixed_amount');
        });
    }
};
