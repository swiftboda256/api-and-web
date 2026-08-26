<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->enum('service_type', ['ride', 'delivery']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('service_catalogs', function (Blueprint $table) {
            $table->dropColumn('service_type');
        });
    }
};
