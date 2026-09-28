<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The lowest a rider's wallet balance may drop to in this zone when the commission on a
     * cash trip is deducted from it (e.g. -20000). 0 means no negative balance is allowed.
     */
    public function up(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->decimal('minimum_negative_balance', 12, 2)->default(0)->after('timezone');
        });
    }

    public function down(): void
    {
        Schema::table('zones', function (Blueprint $table) {
            $table->dropColumn('minimum_negative_balance');
        });
    }
};
