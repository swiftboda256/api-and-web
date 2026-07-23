<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('device_type', ['ios', 'android', 'web']);
            $table->string('device_id')->nullable();
            $table->string('fcm_token')->nullable();
            $table->string('app_version')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->boolean('active')->default(true);
            $table->auditColumns();

            $table->unique(['user_id', 'device_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_devices');
    }
};
