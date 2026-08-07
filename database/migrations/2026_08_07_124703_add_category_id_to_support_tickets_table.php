<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('support_tickets')->delete();

        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreignId('category_id')->after('trip_id')->constrained('support_categories')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('support_tickets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->enum('category', ['trip_issue', 'payment', 'account', 'vehicle', 'other'])->nullable()->after('trip_id');
        });
    }
};
