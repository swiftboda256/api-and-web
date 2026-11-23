<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Null means "due now", so existing pending transactions are checked straight away.
            $table->timestamp('next_status_check_at')->nullable();
            $table->unsignedInteger('status_check_attempts')->default(0);
            $table->string('last_gateway_status')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['next_status_check_at', 'status_check_attempts', 'last_gateway_status']);
        });
    }
};
