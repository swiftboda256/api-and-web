<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->unsignedTinyInteger('capacity')->default(1);
            $table->string('icon_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_types');
    }
};
