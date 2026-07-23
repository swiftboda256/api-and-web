<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('recipient_name');
            $table->string('recipient_phone');
            $table->string('package_description')->nullable();
            $table->enum('package_size', ['small', 'medium', 'large'])->default('small');
            $table->decimal('package_weight_kg', 6, 2)->nullable();
            $table->boolean('requires_signature')->default(false);
            $table->string('proof_of_delivery_photo')->nullable();
            $table->string('delivered_to_name')->nullable();
            $table->string('delivery_notes')->nullable();
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_details');
    }
};
