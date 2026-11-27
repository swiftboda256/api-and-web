<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->foreignId('sms_cost_acknowledged_by')->nullable()->after('sms_estimated_cost')->constrained('users')->nullOnDelete();
            $table->timestamp('sms_cost_acknowledged_at')->nullable()->after('sms_cost_acknowledged_by');
            $table->unsignedBigInteger('sms_cost_acknowledged_amount')->nullable()->after('sms_cost_acknowledged_at');
        });
    }

    public function down(): void
    {
        Schema::table('communications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sms_cost_acknowledged_by');
            $table->dropColumn(['sms_cost_acknowledged_at', 'sms_cost_acknowledged_amount']);
        });
    }
};
