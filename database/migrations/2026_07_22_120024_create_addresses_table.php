<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->geometry('location', subtype: 'point', srid: 4326);
            $table->string('formatted_address')->nullable();
            $table->string('place_id')->nullable();
            $table->boolean('is_default')->default(false);
            $table->auditColumns();

            $table->spatialIndex('location');
            $table->index(['user_id', 'is_default']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
