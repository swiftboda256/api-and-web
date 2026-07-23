<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('rider_profile_id')->constrained()->cascadeOnDelete();
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->decimal('heading', 5, 2)->nullable();
            $table->decimal('speed', 6, 2)->nullable();
            $table->timestamp('recorded_at');
            $table->auditColumns();

            $table->spatialIndex('location');
            $table->index(['trip_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_locations');
    }
};
