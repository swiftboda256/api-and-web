<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->foreignId('trip_id')->nullable()->after('support_ticket_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trip_id');
        });
    }
};
