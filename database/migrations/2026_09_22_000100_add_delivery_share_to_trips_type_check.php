<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_type_check');
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_type_check CHECK (type IN ('ride', 'delivery', 'ride_share', 'delivery_share'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE trips DROP CONSTRAINT trips_type_check');
        DB::statement("ALTER TABLE trips ADD CONSTRAINT trips_type_check CHECK (type IN ('ride', 'delivery', 'ride_share'))");
    }
};
