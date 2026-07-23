<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rider_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('rider_ref')->nullable()->index();
            $table->string('national_id_number')->nullable();
            $table->string('license_number')->nullable();
            $table->date('license_expiry_at')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->enum('kyc_status', ['pending', 'under_review', 'approved', 'rejected'])->default('pending');
            $table->string('kyc_rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('availability_status', ['offline', 'online', 'on_trip'])->default('offline');
            $table->geometry('current_location', subtype: 'point', srid: 4326)->nullable();
            $table->timestamp('last_location_at')->nullable();
            $table->foreignId('home_zone_id')->nullable()->constrained('zones')->nullOnDelete();
            $table->unsignedInteger('total_trips')->default(0);
            $table->decimal('total_earnings', 12, 2)->default(0);
            $table->auditColumns();

            $table->spatialIndex('current_location');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_profiles');
    }
};
