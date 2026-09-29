<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE otps DROP CONSTRAINT otps_purpose_check');
        DB::statement("ALTER TABLE otps ADD CONSTRAINT otps_purpose_check CHECK (purpose::text = ANY (ARRAY['login', 'signup', 'password_reset', 'phone_verify', 'email_verify', 'wallet_pin_reset']::text[]))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE otps DROP CONSTRAINT otps_purpose_check');
        DB::statement("ALTER TABLE otps ADD CONSTRAINT otps_purpose_check CHECK (purpose::text = ANY (ARRAY['login', 'signup', 'password_reset', 'phone_verify', 'email_verify']::text[]))");
    }
};
