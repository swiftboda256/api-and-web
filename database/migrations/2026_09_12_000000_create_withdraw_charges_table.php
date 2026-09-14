<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdraw_charges', function (Blueprint $table) {
            $table->id();
            $table->enum('channel', ['mobile_money', 'bank_account', 'wallet'])->nullable();
            $table->decimal('min_amount', 12, 2)->default(0);
            $table->decimal('max_amount', 12, 2)->nullable();
            $table->decimal('base_charge', 10, 2)->default(0);
            $table->decimal('mtn_charge', 10, 2)->default(0);
            $table->decimal('airtel_charge', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->auditColumns();

            $table->index(['channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdraw_charges');
    }
};
