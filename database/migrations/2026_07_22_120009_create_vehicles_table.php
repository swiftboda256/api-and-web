<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rider_profile_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_type_id')->constrained();
            $table->string('make')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color')->nullable();
            $table->string('plate_number')->unique();
            $table->string('registration_number')->nullable();
            $table->date('insurance_expiry_at')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'inactive'])->default('pending');
            $table->auditColumns();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
