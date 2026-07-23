<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('method', ['wallet', 'cash', 'mobile_money', 'card']);
            $table->enum('direction', ['credit', 'debit']);
            $table->enum('transaction_type', ['topup', 'trip_payment', 'trip_payout', 'refund', 'withdrawal', 'promo_credit', 'adjustment']);
            $table->decimal('amount', 12, 2);
            $table->string('currency_code', 3);
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable()->index();
            $table->string('phone')->nullable();
            $table->string('narration')->nullable();
            $table->nullableMorphs('reference');
            $table->enum('status', ['pending', 'completed', 'failed', 'reversed'])->default('pending');
            $table->string('failure_reason')->nullable();

            $table->auditColumns();

            $table->index(['user_id', 'transaction_type']);
            $table->index(['wallet_id', 'transaction_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
