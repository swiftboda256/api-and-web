<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            // delivery_share only: cargo-weight equivalent of available_seats -- a
            // separate counter since it's a different capacity dimension (kg, not seats).
            $table->decimal('available_cargo_weight_kg', 8, 2)->nullable()->after('passenger_count');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('available_cargo_weight_kg');
        });
    }
};
