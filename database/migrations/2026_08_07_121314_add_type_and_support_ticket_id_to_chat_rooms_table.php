<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->enum('type', ['one_to_one', 'support'])->default('one_to_one')->after('id');
            $table->foreignId('support_ticket_id')->nullable()->after('type')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('support_ticket_id');
            $table->dropColumn('type');
        });
    }
};
