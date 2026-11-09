<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE chat_rooms DROP CONSTRAINT chat_rooms_type_check');
        DB::statement("ALTER TABLE chat_rooms ADD CONSTRAINT chat_rooms_type_check CHECK (type IN ('one_to_one', 'support', 'trip'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE chat_rooms DROP CONSTRAINT chat_rooms_type_check');
        DB::statement("ALTER TABLE chat_rooms ADD CONSTRAINT chat_rooms_type_check CHECK (type IN ('one_to_one', 'support'))");
    }
};
