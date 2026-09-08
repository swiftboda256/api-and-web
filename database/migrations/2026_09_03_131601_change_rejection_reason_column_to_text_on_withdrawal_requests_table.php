<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE withdrawal_requests ALTER COLUMN rejection_reason TYPE text USING rejection_reason::text');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE withdrawal_requests ALTER COLUMN rejection_reason TYPE varchar(255) USING rejection_reason::varchar(255)');
    }
};
