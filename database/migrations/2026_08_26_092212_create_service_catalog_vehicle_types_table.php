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
        Schema::create('service_catalog_vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_catalog_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained()->cascadeOnDelete();
            $table->auditColumns();

            $table->unique(['service_catalog_id', 'vehicle_type_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_catalog_vehicle_types');
    }
};
