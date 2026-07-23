<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_cancellation_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->enum('applies_to', ['customer', 'rider', 'both'])->default('both');
            $table->boolean('is_active')->default(true);
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_cancellation_reasons');
    }
};
