<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE transactions ALTER COLUMN failure_reason TYPE text USING failure_reason::text');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('ALTER TABLE transactions ALTER COLUMN failure_reason TYPE varchar(255) USING failure_reason::varchar(255)');
    }
};
