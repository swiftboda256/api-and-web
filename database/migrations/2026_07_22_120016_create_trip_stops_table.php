<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('sequence');
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->string('address')->nullable();
            $table->timestamp('arrived_at')->nullable();
            $table->auditColumns();

            $table->spatialIndex('location');
            $table->unique(['trip_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_stops');
    }
};
