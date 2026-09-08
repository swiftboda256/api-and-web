<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT transactions_transaction_type_check');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_transaction_type_check CHECK (transaction_type IN ('topup', 'trip_payment', 'trip_payout', 'refund', 'withdrawal', 'promo_credit', 'adjustment', 'commission'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transactions DROP CONSTRAINT transactions_transaction_type_check');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_transaction_type_check CHECK (transaction_type IN ('topup', 'trip_payment', 'trip_payout', 'refund', 'withdrawal', 'promo_credit', 'adjustment'))");
    }
};
