<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['rider_profile_id', 'status']);
            $table->dropConstrainedForeignId('rider_profile_id');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('rider_id')->nullable()->after('customer_id')->constrained('users')->nullOnDelete();
            $table->index(['rider_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex(['rider_id', 'status']);
            $table->dropConstrainedForeignId('rider_id');
        });

        Schema::table('trips', function (Blueprint $table) {
            $table->foreignId('rider_profile_id')->nullable()->after('customer_id')->constrained('rider_profiles')->nullOnDelete();
            $table->index(['rider_profile_id', 'status']);
        });
    }
};
