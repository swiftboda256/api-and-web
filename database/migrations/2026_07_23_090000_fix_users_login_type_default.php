<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE users ALTER COLUMN login_type SET DEFAULT 'sms'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE users ALTER COLUMN login_type SET DEFAULT 'phone'");
    }
};
