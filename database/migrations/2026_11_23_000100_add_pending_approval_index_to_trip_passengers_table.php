<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Covers the expiry sweeps, which run every few seconds over the few rows still waiting.
     */
    public function up(): void
    {
        DB::statement("CREATE INDEX trip_passengers_pending_approval_index ON trip_passengers (request_expires_at) WHERE status = 'pending_approval'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS trip_passengers_pending_approval_index');
    }
};
